<?php

namespace PPSFWOO;

use PPSFWOO\PluginMain;

class Exception
{
	public static function log($message = "")
	{
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace 
		$stack_trace = debug_backtrace();

        $message .= " Stack trace:\n";
        
        foreach ($stack_trace as $index => $trace)
        {
            $message .= "#{$index} ";

            if (isset($trace['file'])) {

                $message .= "{$trace['file']}({$trace['line']}): ";

            }

            $message .=  "{$trace['function']}()\n";
        }

        wc_get_logger()->error($message, ['source' => PluginMain::plugin_data("Name")]);
	}
}
