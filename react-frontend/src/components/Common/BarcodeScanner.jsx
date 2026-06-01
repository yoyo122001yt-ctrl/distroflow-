import { useState, useRef } from 'react'
import { Barcode, Search } from 'lucide-react'
import { useTranslation } from 'react-i18next'

export default function BarcodeScanner({ onScan, placeholder }) {
  const { t } = useTranslation()
  const [value, setValue] = useState('')
  const [isScanning, setIsScanning] = useState(false)
  const inputRef = useRef()

  const resolvedPlaceholder = placeholder || t('barcodeScanner:placeholder')

  const handleSubmit = (e) => {
    e.preventDefault()
    if (value.trim()) {
      onScan(value.trim())
      setValue('')
    }
  }

  return (
    <form onSubmit={handleSubmit} className="flex items-center gap-2">
      <div className="relative flex-1">
        <Barcode size={18} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
        <input
          ref={inputRef}
          type="text"
          value={value}
          onChange={e => setValue(e.target.value)}
          placeholder={resolvedPlaceholder}
          className="input-field pl-10"
          autoFocus={isScanning}
        />
      </div>
      <button
        type="button"
        onClick={() => { setIsScanning(s => !s); if (!isScanning) inputRef.current?.focus() }}
        className={`btn-secondary !p-2 ${isScanning ? 'ring-2 ring-brand-500 bg-brand-50' : ''}`}
        title={isScanning ? t('barcodeScanner:scannerActive') : t('barcodeScanner:activateScanner')}
      >
        <Search size={18} />
      </button>
    </form>
  )
}
