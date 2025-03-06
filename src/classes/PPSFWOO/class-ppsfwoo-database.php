<?php

namespace PPSFWOO;

use PPSFWOO\PluginMain,
    PPSFWOO\AjaxActionsPriv;

class Database
{
	public $result;

    protected static $version = "2.4";

    // phpcs:disable
	public function __construct($query, $vars = [], $output = OBJECT)
	{
		global $wpdb;

        $wpdb->query('SET time_zone = "+00:00"');
        
        $stmt = $vars ? $wpdb->prepare($query, $vars): $query;

        if(strpos($query, "SELECT") === 0) {

        	$result = $wpdb->get_results($stmt, $output);

        } else {

        	$result = $wpdb->query($stmt);

        }

        if(!empty($wpdb->last_error)) {

        	$this->result = ['error'  => $wpdb->last_error];

        } else {

        	$this->result = $result;

        }

	}
    // phpcs:enable

    public static function install()
    {
        if((new self("SHOW TABLES LIKE '{$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber';"))->result)

            return;

        new self("CREATE TABLE IF NOT EXISTS {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber (
          id varchar(64) NOT NULL,
          wp_customer_id bigint(20) UNSIGNED NOT NULL,
          paypal_plan_id varchar(64) NOT NULL,
          order_id bigint(20) UNSIGNED DEFAULT NULL,
          event_type varchar(35) NOT NULL,
          created datetime DEFAULT current_timestamp(),
          last_updated datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          canceled_date datetime DEFAULT NULL,
          PRIMARY KEY (id),
          KEY idx_wp_customer_id (wp_customer_id),
          KEY idx_order_id (order_id),
          FOREIGN KEY fk_user_id (wp_customer_id)
            REFERENCES {$GLOBALS['wpdb']->base_prefix}users(ID)
            ON UPDATE CASCADE ON DELETE CASCADE,
          FOREIGN KEY fk_order_id (order_id)
            REFERENCES {$GLOBALS['wpdb']->base_prefix}wc_orders(id)
            ON UPDATE CASCADE ON DELETE CASCADE
        );");

        update_option('ppsfwoo_db_version', self::$version, false);
    }

    public static function upgrade()
    {
        $installed_version = PluginMain::get_option('ppsfwoo_db_version') ?: self::$version;

        $this_version = PluginMain::plugin_data('Version');

        if($installed_version === $this_version) return;

        if (version_compare($installed_version, '2.4.1', '<')) {

            new self("ALTER TABLE {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber
                ADD COLUMN `expires` datetime DEFAULT NULL,
                ADD INDEX `idx_expires` (`expires`);"
            );
        }

        if (version_compare($installed_version, '2.4.2', '<')) {

            new self("ALTER TABLE {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber
                MODIFY `expires` date,
                DROP INDEX `idx_expires`,
                ADD INDEX `idx_expires` (`expires`);"
            );
        }

        if (version_compare($installed_version, '2.4.6', '<')) {

            AjaxActionsPriv::refresh_plans();

        }

        update_option('ppsfwoo_db_version', $this_version, false);

        wp_cache_delete('ppsfwoo_db_version', 'options');
        
    }

    public static function export()
    {
        global $wpdb;

        $data = new self("SELECT * FROM {$wpdb->prefix}ppsfwoo_subscriber", [], ARRAY_A);

        if(!isset($data->result[0])) {

            exit;

        }

        $columns = array_keys($data->result[0]);

        $column_names = "`" . implode('`, `', $columns) . "`";

        $values = [];

        foreach ($data->result as $row)
        {
            $escaped_values = array_map(function($value) use ($wpdb) {

                if (is_null($value)) {

                    return 'NULL';

                } else if (ctype_digit($value)) {

                    return $wpdb->prepare('%d', $value);

                } else {

                    return $wpdb->prepare('%s', $value);

                }

            }, $row);

            $values[] = '(' . implode(', ', $escaped_values) . ')';

        }

        $db_name = DB_NAME;

        return "INSERT INTO `{$db_name}`.`{$wpdb->prefix}ppsfwoo_subscriber` ({$column_names}) VALUES " . implode(', ', $values) . ';';
    }
}
