<?php

if (! function_exists('loadInlineStylesheet')) {
    /**
     * Load and wrap stylesheet resources in HTML style tags for inline rendering.
     */
    function loadInlineStylesheet(string ...$relativePaths): string
    {
        $output = '';

        foreach ($relativePaths as $relativePath) {
            $path = resource_path($relativePath);

            if (! file_exists($path)) {
                continue;
            }

            $content = file_get_contents($path);

            if ($content === false) {
                continue;
            }

            $output .= '<style>' . $content . '</style>';
        }

        return $output;
    }
}

if (! function_exists('loadInlineScript')) {
    /**
     * Load and wrap JavaScript resources in HTML script tags for inline rendering.
     */
    function loadInlineScript(string ...$relativePaths): string
    {
        $output = '';

        foreach ($relativePaths as $relativePath) {
            $path = resource_path($relativePath);

            if (! file_exists($path)) {
                continue;
            }

            $content = file_get_contents($path);

            if ($content === false) {
                continue;
            }

            $output .= '<script>' . $content . '</script>';
        }

        return $output;
    }
}
