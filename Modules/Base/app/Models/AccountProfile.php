<?php

namespace Modules\Base\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 模型：账号资料
 */
class AccountProfile extends Model
{
    protected $table = 'gpa_account_profile';

    protected $fillable = [
        'id',
        'account_id',
        'nickname',
        'gender',
        'avatar',
        'verification_method',
        'verified_at',
        'id_card_no',
        'id_card_name',
        'id_card_valid_from',
        'id_card_valid_to',
        'id_card_issuer',
        'id_card_front_url',
        'id_card_back_url',
        'id_card_verify_status',
    ];

    protected $casts = [
        'verified_at'           => 'datetime',
        'id_card_valid_from'    => 'date',
        'id_card_valid_to'      => 'date',
        'id_card_verify_status' => 'integer',
    ];

    /**
     * 关联账号
     * @return BelongsTo
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
