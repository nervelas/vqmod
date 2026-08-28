<?php
declare(strict_types=1);

namespace MenuGold\Models;

use MenuGold\Core\Model;

class Zone extends Model
{
    protected string $table = 'zones';
    protected array $fillable = ['restaurant_id','nombre','orden'];
}
