<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ProductsImport implements ToCollection, WithHeadingRow, WithValidation
{
    protected int $imported = 0;
    protected int $failed = 0;
    protected array $errors = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            try {
                $category = null;
                if (!empty($row['category'])) {
                    $category = ProductCategory::firstOrCreate(
                        ['name' => $row['category']],
                        ['slug' => \Illuminate\Support\Str::slug($row['category']), 'is_active' => true]
                    );
                }

                Product::create([
                    'name' => $row['name'],
                    'sku' => $row['sku'],
                    'category_id' => $category?->id,
                    'selling_price' => (float) ($row['selling_price'] ?? 0),
                    'cost_price' => (float) ($row['cost_price'] ?? 0),
                    'stock_quantity' => (int) ($row['stock_quantity'] ?? 0),
                    'unit' => $row['unit'] ?? 'piece',
                    'description' => $row['description'] ?? null,
                    'is_active' => in_array($row['is_active'] ?? '1', ['1', 'true', 'yes', 'active']),
                ]);

                $this->imported++;
            } catch (\Throwable $e) {
                $this->failed++;
                $this->errors[] = "Row '{$row['name']}': {$e->getMessage()}";
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products,sku',
            'selling_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
        ];
    }

    public function getImportedCount(): int
    {
        return $this->imported;
    }

    public function getFailedCount(): int
    {
        return $this->failed;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
