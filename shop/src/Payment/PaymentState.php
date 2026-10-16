<?php

namespace Shop\Payment;

// Slide 22 — "Not the states we remembered. All of them."
enum PaymentState: string
{
    case Created  = 'CREATED';
    case Pending  = 'PENDING';
    case Analysis = 'ANALYSIS';
    case Paid     = 'PAID';
    case Failed   = 'FAILED';
    case Refunded = 'REFUNDED';
    case Closed   = 'CLOSED';
}
