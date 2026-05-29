<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case Purchase = 'purchase';
    case PurchaseRefund = 'purchase_refund';
    case Sale = 'sale';
    case SaleRefund = 'sale_refund';
}
