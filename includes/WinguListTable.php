<?php

declare(strict_types=1);

namespace Wingu\Plugin\Wordpress;

use Wingu\Engine\SDK\Model\Request\Channel\PrivateChannelsFilter;
use Wingu\Engine\SDK\Model\Request\Channel\PrivateChannelsSorting;
use Wingu\Engine\SDK\Model\Request\RequestParameters;
use Wingu\Engine\SDK\Model\Response\Channel\Beacon\PrivateBeacon;
use Wingu\Engine\SDK\Model\Response\Channel\Geofence\PrivateGeofence;
use Wingu\Engine\SDK\Model\Response\Channel\Nfc\PrivateNfc;
use Wingu\Engine\SDK\Model\Response\Channel\QrCode\PrivateQrCode;

class WinguListTable extends ListTable
{
    public function __construct()
    {
        parent::__construct([
            'singular' => 'trigger',
            'plural' => 'triggers',
            'ajax' => true,
        ]);
    }

    public function column_name($item)
    {
        return sprintf('%1$s',
            $item['name']
        );
    }

    public function column_type($item)
    {
        return sprintf('%1$s',
            $item['type']
        );
    }

    public function column_content($item)
    {
        $actions = [];
        if ($item['contentid'] === null) {
            $actions['link'] = sprintf('<a href="?page=%s&action=%s&trigger=%s&content=%s">Link</a>', $_REQUEST['page'],
                'link', $item['id'], $item['contentid']);
        } else {
            $actions['unlink'] = sprintf('<a href="?page=%s&action=%s&trigger=%s&content=%s">Unlink</a>',
                $_REQUEST['page'], 'unlink', $item['id'], $item['contentid']);
        }

        return sprintf('%1$s %2$s',
            $item['content'],
            $this->row_actions($actions)
        );
    }

    public function get_sortable_columns() : array
    {
        $sortable_columns = [
            'name' => ['name', true],
            'type' => ['type', false],
        ];
        return $sortable_columns;
    }

    public function get_columns() : array
    {
        $columns = [
            'name' => 'Name',
            'type' => 'Type',
            'content' => 'Content',
        ];
        return $columns;
    }

    public function prepare_items() : void
    {
        $per_page              = 10;
        $columns               = $this->get_columns();
        $hidden                = [];
        $sortable              = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        if (isset($_GET['s'])) {
            $data                  = $this->winguChannelApiQuery(new PrivateChannelsFilter(null, $_GET['s']),
                new PrivateChannelsSorting(null, RequestParameters::SORTING_ORDER_ASC));
            usort($data, [$this, 'usort_reorder']);
        } else {
            $data = $this->winguChannelApiQuery(null,
                new PrivateChannelsSorting(null, RequestParameters::SORTING_ORDER_ASC));
            usort($data, [$this, 'usort_reorder']);
        }

        $current_page = $this->get_pagenum();
        $total_items  = \count($data);
        $data         = \array_slice($data, ($current_page - 1) * $per_page, $per_page);
        $this->items  = $data;

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page),
            'orderby' => ! empty($_REQUEST['orderby']) && $_REQUEST['orderby'] !== '' ? $_REQUEST['orderby'] : 'name',
            'order' => ! empty($_REQUEST['order']) && $_REQUEST['order'] !== '' ? $_REQUEST['order'] : 'ASC',
        ]);
    }

    /** @return mixed[] */
    private function winguChannelApiQuery(
        ?PrivateChannelsFilter $filters = null,
        ?PrivateChannelsSorting $sorting = null
    ) : array {
        $winguChannelApi = Wingu::$API->channel();
        $data            = [];

        $response = $winguChannelApi->myChannels($filters, $sorting);
        while ($response->valid()) {
            $current     = $response->current();
            $channelname = $current->name();
            $type        = \get_class($current);
            $channeltype = null;
            switch ($type) {
                case PrivateGeofence::class:
                    $channeltype = 'Geofence';
                    break;
                case PrivateQrCode::class:
                    $channeltype = 'QRcode';
                    break;
                case PrivateNfc::class:
                    $channeltype = 'NFC';
                    break;
                case PrivateBeacon::class:
                    $channeltype = 'Beacon';
                    break;
            }

            $data[] = [
                'id' => $current->id(),
                'name' => $channelname,
                'type' => $channeltype,
                'content' => $current->content() ? $current->content()->title() : '<i>No content attached</i>',
                'contentid' => $current->content() ? $current->content()->id() : null,
            ];

            $response->next();
        }

        return $data;
    }

    private function usort_reorder($a, $b)
    {
        $orderby = ! empty($_REQUEST['orderby']) ? $_REQUEST['orderby'] : 'name'; //If no sort, default to title
        $order   = ! empty($_REQUEST['order']) ? $_REQUEST['order'] : 'ASC'; //If no order, default to asc
        $result  = strnatcmp($a[$orderby], $b[$orderby]); //Determine sort order
        return ($order === 'ASC') ? $result : -$result; //Send final sort direction to usort
    }

    public function display() : void
    {

        wp_nonce_field( 'ajax-wingu-triggers-nonce', '_ajax_wingu_triggers_nonce' );

        echo '<input id="order" type="hidden" name="order" value="' . $this->_pagination_args['order'] . '" />';
        echo '<input id="orderby" type="hidden" name="orderby" value="' . $this->_pagination_args['orderby'] . '" />';
//        if (isset($_REQUEST['s'])) {
//            echo '<input id="s" type="hidden" name="s" value="' . $_REQUEST['s'] . '" />';
//        }

        parent::display();
    }

    public function ajax_response() : void  {

        check_ajax_referer( 'ajax-wingu-triggers-nonce', '_ajax_wingu_triggers_nonce' );

        $this->prepare_items();

        extract( $this->_args );
        extract( $this->_pagination_args, EXTR_SKIP );

        ob_start();
        if ( ! empty( $_REQUEST['no_placeholder'] ) )
            $this->display_rows();
        else
            $this->display_rows_or_placeholder();
        $rows = ob_get_clean();

        ob_start();
        $this->print_column_headers();
        $headers = ob_get_clean();

        ob_start();
        $this->pagination('top');
        $pagination_top = ob_get_clean();

        ob_start();
        $this->pagination('bottom');
        $pagination_bottom = ob_get_clean();

        $response = ['rows' => $rows];
        $response['pagination']['top'] = $pagination_top;
        $response['pagination']['bottom'] = $pagination_bottom;
        $response['column_headers'] = $headers;

        if ( isset( $total_items ) )
            $response['total_items_i18n'] = sprintf( _n( '1 item', '%s items', $total_items ), number_format_i18n( $total_items ) );

        if ( isset( $total_pages ) ) {
            $response['total_pages'] = $total_pages;
            $response['total_pages_i18n'] = number_format_i18n( $total_pages );
        }

        die( json_encode( $response ) );
    }
}