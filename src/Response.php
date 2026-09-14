<?php
declare(strict_types=1);

namespace TinyGears\Web;

/**
 * 轻量 HTTP 响应对象。
 * 资源方法返回 Response，由框架统一发送，避免直接输出。
 */
final class Response
{
    public function __construct(
        public readonly string $body = '',
        public readonly int $status = 200,
        public readonly array $headers = [],
    ) {}

    /** 返回 JSON 响应 */
    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            body: json_encode($data, JSON_UNESCAPED_UNICODE),
            status: $status,
            headers: ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    /** 返回 HTML 响应 */
    public static function html(string $html, int $status = 200): self
    {
        return new self(
            body: $html,
            status: $status,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
        );
    }

    /** 返回重定向响应 */
    public static function redirect(string $url, int $status = 302): self
    {
        return new self(status: $status, headers: ['Location' => $url]);
    }

    /** 返回空响应 */
    public static function empty(int $status = 204): self
    {
        return new self(status: $status);
    }

    /** 发送响应到浏览器 */
    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        echo $this->body;
    }
}
