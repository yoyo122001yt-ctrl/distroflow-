import { useState } from 'react'
import { Plus, Minus, Trash2, ShoppingCart } from 'lucide-react'
import { formatCurrency } from '../../utils/formatters'
import { useTranslation } from 'react-i18next'

export default function OrderCart({ items, onUpdateQuantity, onRemoveItem, onClear, products }) {
  const { t } = useTranslation()
  const [search, setSearch] = useState('')

  const filteredProducts = (products || []).filter(p =>
    !items.find(i => i.product_id === p.id) &&
    (p.name.toLowerCase().includes(search.toLowerCase()) ||
     p.sku?.toLowerCase().includes(search.toLowerCase()))
  )

  const total = items.reduce((sum, item) => sum + (item.price || 0) * item.quantity, 0)

  return (
    <div className="card">
      <div className="flex items-center justify-between mb-4">
        <h3 className="font-semibold text-gray-900 flex items-center gap-2">
          <ShoppingCart size={18} />
          {t('orderCart:title', { count: items.length })}
        </h3>
        {items.length > 0 && (
          <button onClick={onClear} className="text-sm text-danger-500 hover:text-danger-700">
            {t('orderCart:clearAll')}
          </button>
        )}
      </div>

      <div className="mb-4">
        <input
          type="text"
          placeholder={t('orderCart:searchPlaceholder')}
          value={search}
          onChange={e => setSearch(e.target.value)}
          className="input-field"
        />
        {search && filteredProducts.length > 0 && (
          <div className="mt-1 border border-gray-200 rounded-lg max-h-40 overflow-y-auto">
            {filteredProducts.slice(0, 10).map(product => (
              <button
                key={product.id}
                className="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 flex items-center justify-between"
                onClick={() => {
                  onUpdateQuantity(product.id, 1, product)
                  setSearch('')
                }}
              >
                <span>{product.name}</span>
                <span className="text-gray-500">{formatCurrency(product.price)}</span>
              </button>
            ))}
          </div>
        )}
      </div>

      {items.length === 0 ? (
        <div className="text-center py-8 text-gray-400">
          <ShoppingCart size={40} className="mx-auto mb-2 opacity-50" />
          <p className="text-sm">{t('orderCart:emptyCart')}</p>
        </div>
      ) : (
        <>
          <div className="space-y-2 max-h-80 overflow-y-auto">
            {items.map(item => (
              <div key={item.product_id} className="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-gray-900 truncate">{item.name}</p>
                  <p className="text-xs text-gray-500">{formatCurrency(item.price)} {t('orderCart:each')}</p>
                </div>
                <div className="flex items-center gap-2 ml-2">
                  <button
                    className="p-1 rounded hover:bg-gray-200"
                    onClick={() => onUpdateQuantity(item.product_id, Math.max(0, item.quantity - 1), item)}
                  >
                    <Minus size={14} />
                  </button>
                  <span className="w-8 text-center text-sm font-medium">{item.quantity}</span>
                  <button
                    className="p-1 rounded hover:bg-gray-200"
                    onClick={() => onUpdateQuantity(item.product_id, item.quantity + 1, item)}
                  >
                    <Plus size={14} />
                  </button>
                  <button
                    className="p-1 rounded hover:bg-red-100 text-red-500 ml-1"
                    onClick={() => onRemoveItem(item.product_id)}
                  >
                    <Trash2 size={14} />
                  </button>
                </div>
              </div>
            ))}
          </div>
          <div className="mt-4 pt-3 border-t border-gray-200 flex items-center justify-between">
            <span className="font-semibold text-gray-900">{t('orderCart:total')}</span>
            <span className="font-bold text-lg text-brand-600">{formatCurrency(total)}</span>
          </div>
        </>
      )}
    </div>
  )
}
