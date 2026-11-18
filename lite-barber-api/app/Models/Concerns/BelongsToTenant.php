<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 *
 * @method static void addGlobalScope(string $identifier, callable $scope)
 * @method static void creating(callable $callback)
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            if (! app()->bound('tenant.shop_id')) {
                return;
            }

            $tenantId = app('tenant.shop_id');

            if ($tenantId !== null) {
                $builder->where($builder->getModel()->qualifyColumn('shop_id'), $tenantId);
            }
        });

        static::creating(function ($model): void {
            if (app()->bound('tenant.shop_id') && $model->shop_id === null) {
                $model->shop_id = app('tenant.shop_id');
            }
        });
    }

    public function scopeForTenant(Builder $builder, int $tenantId): Builder
    {
        return $builder->where($this->qualifyColumn('shop_id'), $tenantId);
    }
}
