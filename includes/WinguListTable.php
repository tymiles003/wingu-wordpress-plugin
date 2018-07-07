<?php

declare(strict_types=1);

namespace Wingu\Plugin\Wordpress;


use Wingu\Engine\SDK\Model\Response\Channel\Beacon\PrivateBeacon;
use Wingu\Engine\SDK\Model\Response\Channel\Geofence\PrivateGeofence;
use Wingu\Engine\SDK\Model\Response\Channel\Nfc\PrivateNfc;
use Wingu\Engine\SDK\Model\Response\Channel\QrCode\PrivateQrCode;

class WinguListTable extends ListTable
{
    function __construct()
    {
        global $status, $page;

        parent::__construct([
            'singular' => 'trigger',     //singular name of the listed records
            'plural' => 'triggers',    //plural name of the listed records
            'ajax' => false        //does this table support ajax?
        ]);
    }

    function column_name($item)
    {
        return sprintf('%1$s',
            /*$1%s*/
            $item['name']
        );
    }
    function column_type($item)
    {
        return sprintf('%1$s',
            /*$1%s*/
            $item['type']
        );
    }


    function get_columns()
    {
        $columns = [
            'name' => 'Name',
            'type' => 'Type',
        ];
        return $columns;
    }

    public function prepare_items() : void
    {

        $per_page = 10;

        $columns = $this->get_columns();
        $hidden  = [];

        /**
         * REQUIRED. Finally, we build an array to be used by the class for column
         * headers. The $this->_column_headers property takes an array which contains
         * 3 other arrays. One for all columns, one for hidden columns, and one
         * for sortable columns.
         */
        $this->_column_headers = [$columns, $hidden];

        /**
         * Instead of querying a database, we're going to fetch the example data
         * property we created for use in this plugin. This makes this example
         * package slightly different than one you might build on your own. In
         * this example, we'll be using array manipulation to sort and paginate
         * our data. In a real-world implementation, you will probably want to
         * use sort and pagination data to build a custom query instead, as you'll
         * be able to use your precisely-queried data immediately.
         */
        $winguChannelApi = Wingu::$API->channel();
        $data            = [];

        $response = $winguChannelApi->myChannels();
        $response->current();
        while ($response->valid()) {
            $channelname = $response->current()->name();
            $type        = \get_class($response->current());
            $channeltype = null;
            switch ($type) {
                case PrivateGeofence::class:
                    $channeltype = 'Geofence';
                    break;
                case PrivateQrCode::class:
                    $channeltype = 'QrCode';
                    break;
                case PrivateNfc::class:
                    $channeltype = 'Nfc';
                    break;
                case PrivateBeacon::class:
                    $channeltype = 'Beacon';
                    break;
            }
            $data[] = [
                'id' => $response->current()->id(),
                'name' => $channelname,
                'type' => $channeltype,
            ];
            $response->next();
        }

        /**
         * REQUIRED for pagination. Let's figure out what page the user is currently
         * looking at. We'll need this later, so you should always include it in
         * your own package classes.
         */
        $current_page = $this->get_pagenum();

        /**
         * REQUIRED for pagination. Let's check how many items are in our data array.
         * In real-world use, this would be the total number of items in your database,
         * without filtering. We'll need this later, so you should always include it
         * in your own package classes.
         */
        $total_items = \count($data);


        /**
         * The WP_List_Table class does not handle pagination for us, so we need
         * to ensure that the data is trimmed to only the current page. We can use
         * array_slice() to
         */
        $data = array_slice($data, (($current_page - 1) * $per_page), $per_page);


        /**
         * REQUIRED. Now we can add our *sorted* data to the items property, where
         * it can be used by the rest of the class.
         */
        $this->items = $data;


        /**
         * REQUIRED. We also have to register our pagination options & calculations.
         */
        $this->set_pagination_args([
            'total_items' => $total_items,                  //WE have to calculate the total number of items
            'per_page' => $per_page,                     //WE have to determine how many items to show on a page
            'total_pages' => ceil($total_items / $per_page)   //WE have to calculate the total number of pages
        ]);
    }
}