<?php

namespace App\Exceptions;

use RuntimeException;

class OrderCannotProcessException extends RuntimeException
{
    public function __construct(public readonly int $orderId)
    {
        parent::__construct('Đơn hàng chưa thể xử lý. Vui lòng kiểm tra trạng thái đơn hoặc liên hệ hỗ trợ.');
    }
}
