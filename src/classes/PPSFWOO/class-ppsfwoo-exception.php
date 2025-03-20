<?php

namespace PPSFWOO;

class Exception
{
    /**
     * Logs a message with a stack trace to the error log.
     *
     * @param string $message Optional. The message to be logged. Default empty.
     */
    public static function log($message = '')
    {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
        $stack_trace = debug_backtrace();

        $message .= " Stack trace:\n";

        foreach ($stack_trace as $index => $trace) {
            $message .= "#{$index} ";

            if (isset($trace['file'])) {
                $message .= "{$trace['file']}({$trace['line']}): ";
            }

            $message .= "{$trace['function']}()\n";
        }

        wc_get_logger()->error($message, ['source' => PluginMain::plugin_data('Name')]);
    }
}
