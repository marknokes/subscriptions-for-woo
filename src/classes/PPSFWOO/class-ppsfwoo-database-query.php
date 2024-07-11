<?php

namespace PPSFWOO;

class DatabaseQuery
{
	public $result;

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
