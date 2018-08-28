<?php

declare(strict_types=1);

namespace Wingu\Plugin\Wordpress;

use Wingu\Engine\SDK\Model\Request\Channel\PrivateChannelsFilter;
use Wingu\Engine\SDK\Model\Request\Channel\PrivateChannelsSorting;
use Wingu\Engine\SDK\Model\Request\PaginationParameters;
use Wingu\Engine\SDK\Model\Request\RequestParameters;
use Wingu\Engine\SDK\Model\Response\Channel\Beacon\PrivateBeacon;
use Wingu\Engine\SDK\Model\Response\Channel\Geofence\PrivateGeofence;
use Wingu\Engine\SDK\Model\Response\Channel\Nfc\PrivateNfc;
use Wingu\Engine\SDK\Model\Response\Channel\PrivateChannel;
use Wingu\Engine\SDK\Model\Response\Channel\QrCode\PrivateQrCode;
use Wingu\Engine\SDK\Model\Response\Content\PrivateListContent;

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
            $actions['link'] = sprintf('<a href="?page=wingu-options&tab=%s&action=%s&trigger=%s&name=%s&type=%s">Link</a>',
                'link', 'link', $item['id'], $item['name'], $item['type']);
        } else {
            $actions['unlink'] = sprintf('<a href="?page=wingu-options&tab=%s&action=%s&trigger=%s&name=%s&type=%s&contentid=%s&content=%s">Unlink</a>',
                'link', 'unlink', $item['id'], $item['name'], $item['type'], $item['contentid'], $item['content']);
        }

        return sprintf('%1$s %2$s',
            $item['content'],
            $this->row_actions($actions)
        );
    }

    public function get_sortable_columns() : array
    {
        return [
            'name' => ['name', true],
        ];
    }

    public function get_columns() : array
    {
        return [
            'name' => 'Name',
            'type' => 'Type',
            'content' => 'Content',
        ];
    }

    public function prepare_items() : void
    {
        $per_page              = 10;
        $columns               = $this->get_columns();
        $hidden                = [];
        $sortable              = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        $paginationParameters = null;
        $filters              = null;
        $sorting              = null;

        if (isset($_GET['s'])) {
            $filters = new PrivateChannelsFilter(null, \urldecode($_GET['s']));
        }

        if (isset($_GET['paged'])) {
            $paginationParameters = new PaginationParameters((int) $_GET['paged'], $per_page);
        } else {
            $paginationParameters = new PaginationParameters(1, $per_page);
        }

        if (isset($_REQUEST['orderby'], $_REQUEST['order'])) {
            if ($_REQUEST['orderby'] === 'name') {
                $sorting = new PrivateChannelsSorting(null, $_REQUEST['order']);
            }
        } else {
            $sorting = new PrivateChannelsSorting(null, RequestParameters::SORTING_ORDER_ASC);
        }

        [$data, $total_items] = $this->winguChannelApiQuery($paginationParameters, $filters, $sorting);

        $this->items = $data;

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
        PaginationParameters $paginationParameters,
        ?PrivateChannelsFilter $filters = null,
        ?PrivateChannelsSorting $sorting = null
    ) : array {
        $winguChannelApi = Wingu::$API->channel();
        $data            = [];

        $response    = $winguChannelApi->myChannelsPage($paginationParameters, $filters, $sorting);
        $total_items = $response->pageInfo()->total();
        /** @var PrivateChannel $channel */
        foreach ($response->embedded() as $channel) {
            /** @var PrivateListContent $content */
            $content = $channel->content();
            $type    = null;
            switch (\get_class($channel)) {
                case PrivateGeofence::class:
                    $type = 'Geofence';
                    break;
                case PrivateQrCode::class:
                    $type = 'QRcode';
                    break;
                case PrivateNfc::class:
                    $type = 'NFC';
                    break;
                case PrivateBeacon::class:
                    $type = 'Beacon';
                    break;
            }

            $data[] = [
                'id' => $channel->id(),
                'name' => $channel->name(),
                'type' => $type,
                'content' => $content ? $content->title() : '<i>No content attached</i>',
                'contentid' => $content ? $content->id() : null,
            ];
        }

        return [$data, $total_items];
    }

    public function display() : void
    {

        wp_nonce_field('ajax-wingu-triggers-nonce', '_ajax_wingu_triggers_nonce');
        echo '<input id="order" type="hidden" name="order" value="' . $this->_pagination_args['order'] . '" />';
        echo '<input id="orderby" type="hidden" name="orderby" value="' . $this->_pagination_args['orderby'] . '" />';

        parent::display();
    }

    public function ajax_response() : void
    {

        check_ajax_referer('ajax-wingu-triggers-nonce', '_ajax_wingu_triggers_nonce');

        $this->prepare_items();

        extract($this->_args, EXTR_OVERWRITE);
        extract($this->_pagination_args, EXTR_SKIP);

        ob_start();
        if (! empty($_REQUEST['no_placeholder'])) {
            $this->display_rows();
        } else {
            $this->display_rows_or_placeholder();
        }
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

        $response                         = ['rows' => $rows];
        $response['pagination']['top']    = $pagination_top;
        $response['pagination']['bottom'] = $pagination_bottom;
        $response['column_headers']       = $headers;

        if ($total_items !== null) {
            $response['total_items_i18n'] = sprintf(_n('1 item', '%s items', $total_items),
                number_format_i18n($total_items));
        }

        if ($total_pages !== null) {
            $response['total_pages']      = $total_pages;
            $response['total_pages_i18n'] = number_format_i18n($total_pages);
        }

        die(json_encode($response));
    }
}