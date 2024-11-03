<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\VtSubscription;
use App\Models\VtPlan;
use Carbon\Carbon;

class VtSubscriptionService
{
    public function createSubscription(Restaurant $restaurant, VtPlan $plan, string $billingCycle, ?Carbon $trialEndsAt = null)
    {
        $currentPeriodStart = Carbon::now();
        $currentPeriodEnd = $this->calculatePeriodEnd($currentPeriodStart, $billingCycle);

        return VtSubscription::create([
            'restaurant_id' => $restaurant->id,
            'vt_plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => $billingCycle,
            'trial_ends_at' => $trialEndsAt,
            'current_period_start' => $currentPeriodStart,
            'current_period_end' => $currentPeriodEnd,
        ]);
    }

    public function updateSubscription(VtSubscription $subscription, VtPlan $newPlan = null, string $newBillingCycle = null): VtSubscription
    {
        $data = [];
        if ($newPlan) {
            $data['vt_plan_id'] = $newPlan->id;
        }
        if ($newBillingCycle) {
            $data['billing_cycle'] = $newBillingCycle;
            $data['current_period_end'] = $this->calculatePeriodEnd($subscription->current_period_start, $newBillingCycle);
        }
        $subscription->update($data);
        return $subscription;
    }

    public function cancelSubscription(VtSubscription $subscription): void
    {
        $subscription->update([
            'status' => 'canceled',
            'canceled_at' => Carbon::now(),
        ]);
    }

    public function renewSubscription(VtSubscription $subscription): void
    {
        $newPeriodStart = $subscription->current_period_end;
        $newPeriodEnd = $this->calculatePeriodEnd($newPeriodStart, $subscription->billing_cycle);

        $subscription->update([
            'current_period_start' => $newPeriodStart,
            'current_period_end' => $newPeriodEnd,
            'status' => 'active',
            'canceled_at' => null,
        ]);
    }

    private function calculatePeriodEnd(Carbon $start, string $billingCycle): Carbon
    {
        return $billingCycle === 'monthly' ? $start->addMonth() : $start->addYear();
    }

    public function checkAndUpdateSubscriptionStatus(VtSubscription $subscription): void
    {
        if ($subscription->status === 'active' && $subscription->current_period_end->isPast()) {
            if ($subscription->canceled_at) {
                $subscription->update(['status' => 'expired']);
            } else {
                $this->renewSubscription($subscription);
            }
        }
    }
}
