<?php

class Response {
    public static function json($data, $status = 200, $message = '') {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode([
            'status' => $status >= 200 && $status < 300 ? 'success' : 'error',
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }

    public static function error($message, $status = 400, $data = null) {
        self::json($data, $status, $message);
    }
}
