<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotifyErrorException;
use App\Models\User;
use App\Models\Wallet as WalletModel;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * WalletService
 *
 * Centralized service for managing wallet operations including balance management,
 * wallet creation, fee calculations, and validation. Handles all wallet-related
 * business logic and currency conversions.
 *
 * @author WRPay Team
 *
 * @version 1.0.0
 */
class WalletService
{

    /**
     * Get a wallet by its unique UUID
     *
     * Retrieves a wallet using its unique UUID identifier.
     *
     * @param  string  $uuid  The wallet's unique UUID
     * @return WalletModel The wallet model
     *
     * @throws NotifyErrorException When wallet is not found
     *
     * @example
     * $wallet = $walletService->getWalletByUuId('1234567890123456');
     */
    public function getWalletByUuId(string $uuid): WalletModel
    {
        $wallet = WalletModel::where('uuid', $uuid)->first();

        if (! $wallet) {
            throw NotifyErrorException::error(
                __('Wallet with ID :id not found.', ['id' => $uuid]),
                context: ['wallet_uuid' => $uuid],
                status: HttpResponse::HTTP_NOT_FOUND,
            );
        }

        return $wallet;
    }

    /**
     * Add money to a wallet by its UUID
     *
     * Adds funds to a wallet using its unique UUID identifier.
     * Validates the amount and updates the wallet balance.
     *
     * @param  string  $walletUuid  The wallet's unique UUID
     * @param  float  $amount  The amount to add
     * @return WalletModel The updated wallet model
     *
     * @throws NotifyErrorException When amount is invalid or wallet not found
     *
     * @example
     * $wallet = $walletService->addMoneyByWalletUuid('1234567890123456', 100000);
     */
    public function addMoneyByWalletUuid(string $walletUuid, float $amount): WalletModel
    {
        $wallet = $this->getWalletByUuId($walletUuid);

        return $this->addMoney($wallet, $amount);
    }

    /**
     * Add money to a wallet
     *
     * Adds funds to a wallet and refreshes the model.
     * Validates that the amount is greater than zero.
     *
     * @param  WalletModel  $wallet  The wallet to add money to
     * @param  float  $amount  The amount to add
     * @return WalletModel The updated wallet model
     *
     * @throws NotifyErrorException When amount is invalid
     *
     * @example
     * $wallet = $walletService->addMoney($wallet, 50000);
     */
    public function addMoney(WalletModel $wallet, float $amount): WalletModel
    {
        if ($amount <= 0) {
            throw NotifyErrorException::warning(
                __('Amount must be greater than zero.'),
                status: HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
        if (config('app.mode') === 'sandbox') {
            $wallet->increment('balance_sandbox', $amount);
        }

        $wallet->increment('balance', $amount);

        return $wallet->refresh();
    }

    /**
     * Subtract money from a wallet by its UUID
     *
     * Deducts funds from a wallet using its unique UUID identifier.
     * Validates amount and checks for sufficient balance.
     *
     * @param  string  $walletUuid  The wallet's unique UUID
     * @param  float  $amount  The amount to subtract
     * @return WalletModel The updated wallet model
     *
     * @throws NotifyErrorException When amount is invalid or insufficient balance
     *
     * @example
     * $wallet = $walletService->subtractMoneyByWalletUuid('1234567890123456', 25000);
     */
    public function subtractMoneyByWalletUuid(string $walletUuid, float $amount): WalletModel
    {
        $wallet = $this->getWalletByUuId($walletUuid);

        return $this->subtractMoney($wallet, $amount);
    }

    /**
     * Subtract money from a wallet
     *
     * Deducts funds from a wallet and refreshes the model.
     * Validates amount and checks for sufficient balance.
     *
     * @param  WalletModel  $wallet  The wallet to subtract money from
     * @param  float  $amount  The amount to subtract
     * @return WalletModel The updated wallet model
     *
     * @throws NotifyErrorException When amount is invalid or insufficient balance
     *
     * @example
     * $wallet = $walletService->subtractMoney($wallet, 30000);
     */
    public function subtractMoney(WalletModel $wallet, float $amount): WalletModel
    {
        if ($amount <= 0) {
            throw NotifyErrorException::warning(
                __('Amount must be greater than zero.'),
                status: HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        // Enforce available balance (exclude hold funds)
        if (method_exists($wallet, 'getAvailableBalance')) {
            if ($wallet->getAvailableBalance() < $amount) {
                throw NotifyErrorException::error(
                    __('Insufficient available balance. Some funds are on hold.'),
                    status: HttpResponse::HTTP_BAD_REQUEST,
                );
            }
        } else {
            if ($wallet->balance < $amount) {
                throw NotifyErrorException::error(
                    __('Insufficient balance in wallet.'),
                    status: HttpResponse::HTTP_BAD_REQUEST,
                );
            }
        }

        $wallet->decrementBalance($amount);

        return $wallet->refresh();
    }


    /**
     * Check if a user already has a wallet in a specific currency.
     */
    protected function userHasWalletWithCurrency(User $user, int $currencyId): bool
    {
        return $user->wallets()->where('currency_id', $currencyId)->exists();
    }

}
