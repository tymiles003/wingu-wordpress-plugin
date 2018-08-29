<?php

declare(strict_types=1);

namespace Wingu\Plugin\Wordpress;

class ListTable
{
    /**
     * The current list of items.
     * @var array
     */
    public $items;

    /**
     * Various information about the current table.
     * @var array
     */
    protected $_args;

    /**
     * Various information needed for displaying the pagination.
     * @var array
     */
    protected $_pagination_args = [];

    /**
     * The current screen.
     * @var object
     */
    protected $screen;

    /**
     * Cached pagination output.
     * @var string
     */
    private $_pagination;

    /**
     * The view switcher modes.
     * @var array
     */
    protected $modes = [];

    /**
     * Stores the value returned by ->get_column_info().
     * @var array
     */
    protected $_column_headers;

    /* @var array */
    protected $compat_fields = ['_args', '_pagination_args', 'screen', '_actions', '_pagination'];

    /* @var array */
    protected $compat_methods = [
        'set_pagination_args',
        'get_views',
        'row_actions',
        'get_items_per_page',
        'pagination',
        'get_sortable_columns',
        'get_column_info',
        'get_table_classes',
        'display_tablenav',
        'extra_tablenav',
        'single_row_columns',
    ];

    /**
     * Constructor.
     * The child class should call this constructor from its own constructor to override
     * the default $args.
     * @param array|string $args     {
     *                               Array or string of arguments.
     *
     * @type string        $plural   Plural value used for labels and the objects being listed.
     *                            This affects things such as CSS class-names and nonces used
     *                            in the list table, e.g. 'posts'. Default empty.
     * @type string        $singular Singular label for an object being listed, e.g. 'post'.
     *                            Default empty
     * @type bool          $ajax     Whether the list table supports Ajax. This includes loading
     *                            and sorting data, for example. If true, the class will call
     *                            the _js_vars() method in the footer to provide variables
     *                            to any scripts handling Ajax events. Default false.
     * @type string        $screen   String containing the hook name used to determine the current
     *                            screen. If left null, the current screen will be automatically set.
     *                            Default null.
     */
    public function __construct($args = [])
    {
        $args = wp_parse_args($args, [
            'plural' => '',
            'singular' => '',
            'ajax' => false,
            'screen' => null,
        ]);

        $this->screen = convert_to_screen($args['screen']);

        add_filter("manage_{$this->screen->id}_columns", [$this, 'get_columns'], 0);

        if (! $args['plural']) {
            $args['plural'] = $this->screen->base;
        }

        $args['plural']   = sanitize_key($args['plural']);
        $args['singular'] = sanitize_key($args['singular']);

        $this->_args = $args;

        if ($args['ajax']) {
            add_action('admin_footer', [$this, '_js_vars']);
        }

        if (empty($this->modes)) {
            $this->modes = [
                'list' => 'List View',
                'excerpt' => 'Excerpt View',
            ];
        }
    }

    /**
     * Make private properties readable for backward compatibility.
     * @param string $name Property to get.
     * @return mixed Property.
     */
    public function __get($name)
    {
        if (\in_array($name, $this->compat_fields, true)) {
            return $this->$name;
        }
    }

    /**
     * Make private properties settable for backward compatibility.
     * @param string $name  Property to check if set.
     * @param mixed  $value Property value.
     * @return mixed Newly-set property.
     */
    public function __set($name, $value)
    {
        if (\in_array($name, $this->compat_fields, true)) {
            return $this->$name = $value;
        }
    }

    /**
     * Make private properties checkable for backward compatibility.
     * @param string $name Property to check if set.
     * @return bool Whether the property is set.
     */
    public function __isset($name)
    {
        if (\in_array($name, $this->compat_fields, true)) {
            return isset($this->$name);
        }
    }

    /**
     * Make private properties un-settable for backward compatibility.
     * @param string $name Property to unset.
     */
    public function __unset($name)
    {
        if (\in_array($name, $this->compat_fields, true)) {
            unset($this->$name);
        }
    }

    /**
     * Make private/protected methods readable for backward compatibility.
     * @param callable $name      Method to call.
     * @param array    $arguments Arguments to pass when calling.
     * @return mixed|bool Return value of the callback, false otherwise.
     */
    public function __call($name, $arguments)
    {
        if (\in_array($name, $this->compat_methods, true)) {
            return \call_user_func_array([$this, $name], $arguments);
        }
        return false;
    }

    /**
     * Checks the current user's permissions
     * @abstract
     */
    public function ajax_user_can() : void
    {
        die('function WP_List_Table::ajax_user_can() must be over-ridden in a sub-class.');
    }

    /**
     * Prepares the list of items for displaying.
     * @uses  WP_List_Table::set_pagination_args()
     * @abstract
     */
    public function prepare_items() : void
    {
        die('function WP_List_Table::prepare_items() must be over-ridden in a sub-class.');
    }

    /**
     * An internal method that sets all the necessary pagination arguments
     * @param array|string $args Array or string of arguments with information about the pagination.
     */
    protected function set_pagination_args($args) : void
    {
        $args = wp_parse_args($args, [
            'total_items' => 0,
            'total_pages' => 0,
            'per_page' => 0,
        ]);

        if (! $args['total_pages'] && $args['per_page'] > 0) {
            $args['total_pages'] = ceil($args['total_items'] / $args['per_page']);
        }

        // Redirect if page number is invalid and headers are not already sent.
        if ($args['total_pages'] > 0 && !headers_sent() && !wp_doing_ajax() && $this->get_pagenum() > $args['total_pages']) {
            wp_redirect(add_query_arg('paged', $args['total_pages']));
            exit;
        }

        $this->_pagination_args = $args;
    }

    /**
     * Access the pagination args.
     * @param string $key Pagination argument to retrieve. Common values include 'total_items',
     *                    'total_pages', 'per_page', or 'infinite_scroll'.
     * @return int Number of items that correspond to the given pagination argument.
     */
    public function get_pagination_arg($key) : ?int
    {
        if ($key === 'page') {
            return $this->get_pagenum();
        }

        if (isset($this->_pagination_args[$key])) {
            return $this->_pagination_args[$key];
        }
    }

    /**
     * Whether the table has items to display or not
     */
    public function has_items() : bool
    {
        return ! empty($this->items);
    }

    /**
     * Message to be displayed when there are no items
     */
    public function no_items() : void
    {
        _e('No triggers found.', Wingu::name());
    }

    /**
     * Displays the search box.
     * @param string $text     The 'submit' button label.
     * @param string $input_id ID attribute value for the search input field.
     */
    public function search_box($text, $input_id) : void
    {
        if (empty($_REQUEST['s']) && ! $this->has_items()) {
            return;
        }

        $input_id .= '-search-input';

        if (! empty($_REQUEST['orderby'])) {
            echo '<input type="hidden" name="orderby" value="' . esc_attr($_REQUEST['orderby']) . '" />';
        }
        if (! empty($_REQUEST['order'])) {
            echo '<input type="hidden" name="order" value="' . esc_attr($_REQUEST['order']) . '" />';
        }
        if (! empty($_REQUEST['post_mime_type'])) {
            echo '<input type="hidden" name="post_mime_type" value="' . esc_attr($_REQUEST['post_mime_type']) . '" />';
        }
        if (! empty($_REQUEST['detached'])) {
            echo '<input type="hidden" name="detached" value="' . esc_attr($_REQUEST['detached']) . '" />';
        }
//        unset($_REQUEST['paged']);
//        remove_query_arg('paged');
//        add_query_arg('paged', 1);
//        $_REQUEST['paged'] = 1;
        echo '<input type="hidden" name="paged" value="1" />';

        ?>
        <p class="search-box">
            <label class="screen-reader-text" for="<?php echo esc_attr($input_id); ?>"><?php echo $text; ?>:</label>
            <input type="search" id="<?php echo esc_attr($input_id); ?>" name="s"
                   value="<?php _admin_search_query(); ?>"/>
            <?php submit_button($text, '', '', false, ['id' => 'search-submit']); ?>
        </p>
        <?php
    }

    /**
     * Get an associative array (id => link) with the list
     * of views available on this table.
     * @return array
     */
    protected function get_views() : array
    {
        return [];
    }

    /**
     * Display the list of views available on this table.
     */
    public function views() : void
    {
        $views = $this->get_views();
        /**
         * Filters the list of available list table views.
         * The dynamic portion of the hook name, `$this->screen->id`, refers
         * to the ID of the current screen, usually a string.
         * @param array $views An array of available list table views.
         */
        $views = apply_filters("views_{$this->screen->id}", $views);

        if (empty($views)) {
            return;
        }

        $this->screen->render_screen_reader_content('heading_views');

        echo "<ul class='subsubsub'>\n";
        foreach ($views as $class => $view) {
            $views[$class] = "\t<li class='$class'>$view";
        }
        echo implode(" |</li>\n", $views) . "</li>\n";
        echo '</ul>';
    }

    /**
     * Generate row actions div
     * @param array $actions        The list of actions
     * @param bool  $always_visible Whether the actions should be always visible
     * @return string
     */
    protected function row_actions($actions, $always_visible = false) : string
    {
        $action_count = \count($actions);
        $i            = 0;

        if (! $action_count) {
            return '';
        }

        $out = '<div class="' . ($always_visible ? 'row-actions visible' : 'row-actions') . '">';
        foreach ($actions as $action => $link) {
            ++$i;
            ($i === $action_count) ? $sep = '' : $sep = ' | ';
            $out .= "<span class='$action'>$link$sep</span>";
        }
        $out .= '</div>';

        $out .= '<button type="button" class="toggle-row"><span class="screen-reader-text">' . __('Show more details', Wingu::name()) . '</span></button>';

        return $out;
    }

    /**
     * Get the current page number
     */
    public function get_pagenum() : int
    {
        $pagenum = isset($_REQUEST['paged']) ? absint($_REQUEST['paged']) : 1;

        if (isset($this->_pagination_args['total_pages']) && $pagenum > $this->_pagination_args['total_pages']) {
            $pagenum = $this->_pagination_args['total_pages'];
        }

        return max(1, $pagenum);
    }

    /**
     * Get number of items to display on a single page
     * @param string $option
     * @param int    $default
     * @return int
     */
    protected function get_items_per_page($option, $default = 20) : int
    {
        $per_page = (int) get_user_option($option);
        if (empty($per_page) || $per_page < 1) {
            $per_page = $default;
        }

        /**
         * Filters the number of items to be displayed on each page of the list table.
         * The dynamic hook name, $option, refers to the `per_page` option depending
         * on the type of list table in use. Possible values include: 'edit_comments_per_page',
         * 'sites_network_per_page', 'site_themes_network_per_page', 'themes_network_per_page',
         * 'users_network_per_page', 'edit_post_per_page', 'edit_page_per_page',
         * 'edit_{$post_type}_per_page', etc.
         * @param int $per_page Number of items to be displayed. Default 20.
         */
        return (int) apply_filters((string) $option, $per_page);
    }

    /**
     * Display the pagination.
     * @param string $which
     */
    protected function pagination($which) : void
    {
        if (empty($this->_pagination_args)) {
            return;
        }

        $total_items     = $this->_pagination_args['total_items'];
        $total_pages     = $this->_pagination_args['total_pages'];
        $infinite_scroll = false;
        if (isset($this->_pagination_args['infinite_scroll'])) {
            $infinite_scroll = $this->_pagination_args['infinite_scroll'];
        }

        if ($which === 'top' && $total_pages > 1) {
            $this->screen->render_screen_reader_content('heading_pagination');
        }

        $output = '<span class="displaying-num">' . sprintf(_n('%s trigger', '%s triggers', $total_items, Wingu::name()),
                number_format_i18n($total_items)) . '</span>';

        $current              = $this->get_pagenum();
        $removable_query_args = wp_removable_query_args();

        $current_url = set_url_scheme('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);

        $current_url = remove_query_arg($removable_query_args, $current_url);

        $page_links = [];

        $total_pages_before = '<span class="paging-input">';
        $total_pages_after  = '</span></span>';

        $disable_first = $disable_last = $disable_prev = $disable_next = false;

        if ($current === 1) {
            $disable_first = true;
            $disable_prev  = true;
        }
        if ($current === 2) {
            $disable_first = true;
        }
        if ($current === $total_pages) {
            $disable_last = true;
            $disable_next = true;
        }
        if ($current === $total_pages - 1) {
            $disable_last = true;
        }

        if ($disable_first) {
            $page_links[] = '<span class="tablenav-pages-navspan" aria-hidden="true">&laquo;</span>';
        } else {
            $page_links[] = sprintf("<a class='first-page' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                esc_url(remove_query_arg('paged', $current_url)),
                __('First page', Wingu::name()),
                '&laquo;'
            );
        }

        if ($disable_prev) {
            $page_links[] = '<span class="tablenav-pages-navspan" aria-hidden="true">&lsaquo;</span>';
        } else {
            $page_links[] = sprintf("<a class='prev-page' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                esc_url(add_query_arg('paged', max(1, $current - 1), $current_url)),
                __('Previous page', Wingu::name()),
                '&lsaquo;'
            );
        }

        if ($which === 'bottom') {
            $html_current_page  = $current;
            $total_pages_before = '<span class="screen-reader-text">' . __('Current Page', Wingu::name()) . '</span><span id="table-paging" class="paging-input"><span class="tablenav-paging-text">';
        } else {
            $html_current_page = sprintf("%s<input class='current-page' id='current-page-selector' type='text' name='paged' value='%s' size='%d' aria-describedby='table-paging' /><span class='tablenav-paging-text'>",
                '<label for="current-page-selector" class="screen-reader-text">' . __('Current Page', Wingu::name()) . '</label>',
                $current,
                \strlen((string)$total_pages)
            );
        }
        $html_total_pages = sprintf("<span class='total-pages'>%s</span>", number_format_i18n($total_pages));
        $page_links[]     = $total_pages_before . sprintf(_x('%1$s of %2$s', 'paging', Wingu::name()), $html_current_page,
                $html_total_pages) . $total_pages_after;

        if ($disable_next) {
            $page_links[] = '<span class="tablenav-pages-navspan" aria-hidden="true">&rsaquo;</span>';
        } else {
            $page_links[] = sprintf("<a class='next-page' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                esc_url(add_query_arg('paged', min($total_pages, $current + 1), $current_url)),
                __('Next page', Wingu::name()),
                '&rsaquo;'
            );
        }

        if ($disable_last) {
            $page_links[] = '<span class="tablenav-pages-navspan" aria-hidden="true">&raquo;</span>';
        } else {
            $page_links[] = sprintf("<a class='last-page' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                esc_url(add_query_arg('paged', $total_pages, $current_url)),
                __('Last page', Wingu::name()),
                '&raquo;'
            );
        }

        $pagination_links_class = 'pagination-links';
        if (! empty($infinite_scroll)) {
            $pagination_links_class .= ' hide-if-js';
        }
        $output .= "\n<span class='$pagination_links_class'>" . join("\n", $page_links) . '</span>';

        if ($total_pages) {
            $page_class = $total_pages < 2 ? ' one-page' : '';
        } else {
            $page_class = ' no-pages';
        }
        $this->_pagination = "<div class='tablenav-pages{$page_class}'>$output</div>";

        echo $this->_pagination;
    }

    /**
     * Get a list of columns. The format is:
     * 'internal-name' => 'Title'
     * @abstract
     * @return array
     */
    public function get_columns() : array
    {
        die('function WP_List_Table::get_columns() must be over-ridden in a sub-class.');
    }

    /**
     * Get a list of sortable columns. The format is:
     * 'internal-name' => 'orderby'
     * or
     * 'internal-name' => array( 'orderby', true )
     * The second format will make the initial sorting order be descending
     */
    protected function get_sortable_columns() : array
    {
        return [];
    }

    /**
     * Gets the name of the default primary column.
     * @return string Name of the default primary column, in this case, an empty string.
     */
    protected function get_default_primary_column_name() : string
    {
        $columns = $this->get_columns();
        $column  = '';

        if (empty($columns)) {
            return $column;
        }

        // We need a primary defined so responsive views show something,
        // so let's fall back to the first non-checkbox column.
        foreach ($columns as $col => $column_name) {
            if ($col === 'cb') {
                continue;
            }

            $column = $col;
            break;
        }

        return $column;
    }

    /**
     * Public wrapper for WP_List_Table::get_default_primary_column_name().
     * @return string Name of the default primary column.
     */
    public function get_primary_column() : string
    {
        return $this->get_primary_column_name();
    }

    /**
     * Gets the name of the primary column.
     * @return string The name of the primary column.
     */
    protected function get_primary_column_name() : string
    {
        $columns = get_column_headers($this->screen);
        $default = $this->get_default_primary_column_name();

        // If the primary column doesn't exist fall back to the
        // first non-checkbox column.
        if (! isset($columns[$default])) {
            $default = ListTable::get_default_primary_column_name();
        }

        /**
         * Filters the name of the primary column for the current list table.
         * @param string $default Column name default for the specific list table, e.g. 'name'.
         * @param string $context Screen ID for specific list table, e.g. 'plugins'.
         */
        $column = apply_filters('list_table_primary_column', $default, $this->screen->id);

        if (empty($column) || ! isset($columns[$column])) {
            $column = $default;
        }

        return $column;
    }

    /**
     * Get a list of all, hidden and sortable columns, with filter applied
     * @return array
     */
    protected function get_column_info() : array
    {
        // $_column_headers is already set / cached
        if ($this->_column_headers !== null && \is_array($this->_column_headers)) {
            // Back-compat for list tables that have been manually setting $_column_headers for horse reasons.
            // In 4.3, we added a fourth argument for primary column.
            $column_headers = [[], [], [], $this->get_primary_column_name()];
            foreach ($this->_column_headers as $key => $value) {
                $column_headers[$key] = $value;
            }

            return $column_headers;
        }

        $columns = get_column_headers($this->screen);
        $hidden  = get_hidden_columns($this->screen);

        $sortable_columns = $this->get_sortable_columns();
        /**
         * Filters the list table sortable columns for a specific screen.
         * The dynamic portion of the hook name, `$this->screen->id`, refers
         * to the ID of the current screen, usually a string.
         * @param array $sortable_columns An array of sortable columns.
         */
        $_sortable = apply_filters("manage_{$this->screen->id}_sortable_columns", $sortable_columns);

        $sortable = [];
        foreach ($_sortable as $id => $data) {
            if (empty($data)) {
                continue;
            }

            $data = (array) $data;
            if (! isset($data[1])) {
                $data[1] = false;
            }

            $sortable[$id] = $data;
        }

        $primary               = $this->get_primary_column_name();
        $this->_column_headers = [$columns, $hidden, $sortable, $primary];

        return $this->_column_headers;
    }

    /**
     * Return number of visible columns
     */
    public function get_column_count() : int
    {
        list ($columns, $hidden) = $this->get_column_info();
        $hidden = array_intersect(array_keys($columns), array_filter($hidden));
        return \count($columns) - \count($hidden);
    }

    /**
     * Print column headers, accounting for hidden and sortable columns.
     * @staticvar int $cb_counter
     * @param bool $with_id Whether to set the id attribute or not
     */
    public function print_column_headers($with_id = true) : void
    {
        list($columns, $hidden, $sortable, $primary) = $this->get_column_info();

        $current_url = set_url_scheme('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        $current_url = remove_query_arg('paged', $current_url);

        if (isset($_GET['orderby'])) {
            $current_orderby = $_GET['orderby'];
        } else {
            $current_orderby = '';
        }

        if (isset($_GET['order']) && $_GET['order'] === 'DESC') {
            $current_order = 'DESC';
        } else {
            $current_order = 'ASC';
        }

        if (! empty($columns['cb'])) {
            static $cb_counter = 1;
            $columns['cb'] = '<label class="screen-reader-text" for="cb-select-all-' . $cb_counter . '">Select All</label>'
                . '<input id="cb-select-all-' . $cb_counter . '" type="checkbox" />';
            $cb_counter++;
        }

        foreach ($columns as $column_key => $column_display_name) {
            $class = ['manage-column', "column-$column_key"];

            if (\in_array($column_key, $hidden, true)) {
                $class[] = 'hidden';
            }

            if ($column_key === 'cb') {
                $class[] = 'check-column';
            } elseif (\in_array($column_key, ['posts', 'comments', 'links'])) {
                $class[] = 'num';
            }

            if ($column_key === $primary) {
                $class[] = 'column-primary';
            }

            if (isset($sortable[$column_key])) {
                list($orderby, $desc_first) = $sortable[$column_key];

                if ($current_orderby === $orderby) {
                    $order   = $current_order === 'ASC' ? 'DESC' : 'ASC';
                    $class[] = 'sorted';
                    $class[] = $current_order;
                } else {
                    $order   = $desc_first ? 'DESC' : 'ASC';
                    $class[] = 'sortable';
                    $class[] = $desc_first ? 'ASC' : 'DESC';
                }

                $column_display_name = '<a href="' . esc_url(add_query_arg(compact('orderby', 'order'),
                        $current_url)) . '"><span>' . $column_display_name . '</span><span class="dashicons ' . ($current_order === 'ASC' ? 'dashicons-arrow-up' : 'dashicons-arrow-down') . '"></span></a>';
            }

            $tag   = ($column_key === 'cb') ? 'td' : 'th';
            $scope = ($tag === 'th') ? 'scope="col"' : '';
            $id    = $with_id ? "id='$column_key'" : '';

            if (! empty($class)) {
                $class = "class='" . join(' ', $class) . "'";
            }

            echo "<$tag $scope $id $class>$column_display_name</$tag>";
        }
    }

    /**
     * Display the table
     */
    public function display() : void
    {
        $singular = $this->_args['singular'];

        $this->display_tablenav('top');

        $this->screen->render_screen_reader_content('heading_list');
        ?>
        <table class="wp-list-table <?php echo implode(' ', $this->get_table_classes()); ?>">
            <thead>
            <tr>
                <?php $this->print_column_headers(); ?>
            </tr>
            </thead>

            <tbody id="the-list"<?php
            if ($singular) {
                echo " data-wp-lists='list:$singular'";
            } ?>>
            <?php $this->display_rows_or_placeholder(); ?>
            </tbody>

            <tfoot>
            <tr>
                <?php $this->print_column_headers(false); ?>
            </tr>
            </tfoot>

        </table>
        <?php
        $this->display_tablenav('bottom');
    }

    /**
     * Get a list of CSS classes for the WP_List_Table table tag.
     * @return array List of CSS classes for the table tag.
     */
    protected function get_table_classes() : array
    {
        return ['widefat', 'fixed', 'striped', $this->_args['plural']];
    }

    /**
     * Generate the table navigation above or below the table
     * @param string $which
     */
    protected function display_tablenav($which) : void
    {
        if ($which === 'top') {
            wp_nonce_field('bulk-' . $this->_args['plural']);
        }
        ?>
        <div class="tablenav <?php echo esc_attr($which); ?>">

            <?php
            $this->extra_tablenav($which);
            $this->pagination($which);
            ?>

            <br class="clear"/>
        </div>
        <?php
    }

    /**
     * Extra controls to be displayed between bulk actions and pagination
     * @param string $which
     */
    protected function extra_tablenav($which) : void
    {
    }

    /**
     * Generate the tbody element for the list table.
     */
    public function display_rows_or_placeholder() : void
    {
        if ($this->has_items()) {
            $this->display_rows();
        } else {
            echo '<tr class="no-items"><td class="colspanchange" colspan="' . $this->get_column_count() . '">';
            $this->no_items();
            echo '</td></tr>';
        }
    }

    /**
     * Generate the table rows
     */
    public function display_rows() : void
    {
        foreach ($this->items as $item) {
            $this->single_row($item);
        }
    }

    /**
     * Generates content for a single row of the table
     * @param object $item The current item
     */
    public function single_row($item) : void
    {
        echo '<tr>';
        $this->single_row_columns($item);
        echo '</tr>';
    }

    /**
     * @param object $item
     * @param string $column_name
     */
    protected function column_default($item, $column_name) : void
    {
    }

    /**
     * @param object $item
     */
    protected function column_cb($item) : void
    {
    }

    /**
     * Generates the columns for a single row of the table
     * @param object $item The current item
     */
    protected function single_row_columns($item) : void
    {
        list($columns, $hidden, $sortable, $primary) = $this->get_column_info();

        foreach ($columns as $column_name => $column_display_name) {
            $classes = "$column_name column-$column_name";
            if ($primary === $column_name) {
                $classes .= ' has-row-actions column-primary';
            }

            if (\in_array($column_name, $hidden, true)) {
                $classes .= ' hidden';
            }

            // Comments column uses HTML in the display name with screen reader text.
            // Instead of using esc_attr(), we strip tags to get closer to a user-friendly string.
            $data = 'data-colname="' . wp_strip_all_tags($column_display_name) . '"';

            $attributes = "class='$classes' $data";

            if (method_exists($this, '_column_' . $column_name)) {
                echo $this->{'_column_'.$column_name}($item, $classes, $data, $primary);
            } elseif (method_exists($this, 'column_' . $column_name)) {
                echo "<td $attributes>";
                echo $this->{'column_'.$column_name}($item);
                echo $this->handle_row_actions($item, $column_name, $primary);
                echo '</td>';
            }
        }
    }

    /**
     * Generates and display row actions links for the list table.
     * @param object $item        The item being acted upon.
     * @param string $column_name Current column name.
     * @param string $primary     Primary column name.
     * @return string The row actions HTML, or an empty string if the current column is the primary column.
     */
    protected function handle_row_actions($item, $column_name, $primary) : string
    {
        return $column_name === $primary ? '<button type="button" class="toggle-row"><span class="screen-reader-text">' . __('Show more details', Wingu::name()) . '</span></button>' : '';
    }

    /**
     * Handle an incoming ajax request (called from admin-ajax.php)
     */
    public function ajax_response() : void
    {
        $this->prepare_items();

        ob_start();
        if (empty($_REQUEST['no_placeholder'])) {
            $this->display_rows_or_placeholder();
        } else {
            $this->display_rows();
        }

        $rows = ob_get_clean();

        $response = ['rows' => $rows];

        if (isset($this->_pagination_args['total_items'])) {
            $response['total_items_i18n'] = sprintf(
                _n('%s item', '%s items', $this->_pagination_args['total_items']),
                number_format_i18n($this->_pagination_args['total_items'])
            );
        }

        if (isset($this->_pagination_args['total_pages'])) {
            $response['total_pages']      = $this->_pagination_args['total_pages'];
            $response['total_pages_i18n'] = number_format_i18n($this->_pagination_args['total_pages']);
        }

        die(wp_json_encode($response));
    }

    /**
     * Send required variables to JavaScript land
     */
    public function _js_vars() : void
    {
        $args = [
            'class' => \get_class($this),
            'screen' => [
                'id' => $this->screen->id,
                'base' => $this->screen->base,
            ],
        ];

        printf("<script type='text/javascript'>list_args = %s;</script>\n", wp_json_encode($args));
    }
}