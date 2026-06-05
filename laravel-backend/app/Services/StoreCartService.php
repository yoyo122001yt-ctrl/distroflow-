<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

class StoreCartService
{
    protected string $sessionKey = 'store_cart';

    public function getCart(): Collection
    {
        return collect(Session::get($this->sessionKey, []));
    }

    public function addItem(int $productId, int $quantity = 1, ?float $price = null): array
    {
        $cart = $this->getCart();
        $product = Product::findOrFail($productId);

        if ($existing = $cart->firstWhere('product_id', $productId)) {
            $existing['quantity'] += $quantity;
            $existing['subtotal'] = $existing['quantity'] * ($price ?? $product->selling_price);
            $cart = $cart->replace([$existing]);
        } else {
            $unitPrice = $price ?? $product->selling_price;
            $cart->push([
                'product_id' => $productId,
                'name' => $product->name,
                'sku' => $product->sku,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'subtotal' => $quantity * $unitPrice,
                'image_url' => $product->image_url,
            ]);
        }

        Session::put($this->sessionKey, $cart->values()->toArray());
        return $this->getSummary();
    }

    public function updateQuantity(int $productId, int $quantity): array
    {
        $cart = $this->getCart();
        $item = $cart->firstWhere('product_id', $productId);
        if ($item) {
            if ($quantity <= 0) {
                return $this->removeItem($productId);
            }
            $item['quantity'] = $quantity;
            $item['subtotal'] = $quantity * $item['unit_price'];
            $cart = $cart->replace([$item]);
        }
        Session::put($this->sessionKey, $cart->values()->toArray());
        return $this->getSummary();
    }

    public function removeItem(int $productId): array
    {
        $cart = $this->getCart()->reject(fn($item) => $item['product_id'] === $productId);
        Session::put($this->sessionKey, $cart->values()->toArray());
        return $this->getSummary();
    }

    public function clear(): void
    {
        Session::forget($this->sessionKey);
    }

    public function getSummary(): array
    {
        $cart = $this->getCart();
        $total = $cart->sum('subtotal');
        $count = $cart->sum('quantity');

        return [
            'items' => $cart->values()->toArray(),
            'total' => round($total, 2),
            'count' => $count,
        ];
    }

    public function count(): int
    {
        return $this->getCart()->sum('quantity');
    }

    public function mergeGuestCart(array $savedCart): void
    {
        $current = $this->getCart();
        foreach ($savedCart as $savedItem) {
            $existing = $current->firstWhere('product_id', $savedItem['product_id']);
            if ($existing) {
                $existing['quantity'] = max($existing['quantity'], $savedItem['quantity']);
                $current = $current->replace([$existing]);
            } else {
                $current->push($savedItem);
            }
        }
        Session::put($this->sessionKey, $current->values()->toArray());
    }
}
