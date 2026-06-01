import { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { Settings as SettingsIcon, Save, RefreshCw, Building2, Bell, Shield, Palette } from 'lucide-react'
import toast from 'react-hot-toast'
import api from '../services/api'

const TABS = [
  { id: 'general', label: 'General', icon: Building2 },
  { id: 'notifications', label: 'Notifications', icon: Bell },
  { id: 'security', label: 'Security', icon: Shield },
  { id: 'appearance', label: 'Appearance', icon: Palette },
]

export default function Settings() {
  const { t } = useTranslation()
  const [activeTab, setActiveTab] = useState('general')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [settings, setSettings] = useState({
    company_name: 'DistroFlow',
    company_email: '',
    company_phone: '',
    company_address: '',
    currency: 'EGP',
    timezone: 'America/New_York',
    low_stock_threshold: 10,
    auto_generate_picklists: true,
    require_order_approval: true,
    default_payment_terms: 'net30',
    email_notifications: true,
    sms_notifications: false,
    expiry_alert_days: 30,
  })

  useEffect(() => {
    api.get('/settings').then(({ data }) => {
      if (data) setSettings(prev => ({ ...prev, ...data }))
    }).catch(() => {})
    .finally(() => setLoading(false))
  }, [])

  const handleSave = async () => {
    setSaving(true)
    try {
      await api.post('/settings', settings)
      toast.success(t('settings:saved'))
    } catch (err) {
      toast.error(err.message)
    } finally {
      setSaving(false)
    }
  }

  const update = (key, value) => setSettings(s => ({ ...s, [key]: value }))

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-brand-600"></div>
      </div>
    )
  }

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-2">
          <SettingsIcon size={24} className="text-brand-600" />
          <div>
            <h1 className="text-2xl font-bold text-gray-900">{t('settings:title')}</h1>
            <p className="text-gray-500 mt-1">{t('settings:subtitle')}</p>
          </div>
        </div>
        <button className="btn-primary" onClick={handleSave} disabled={saving}>
          <Save size={18} /> {saving ? t('settings:saving') : t('settings:saveChanges')}
        </button>
      </div>

      <div className="flex gap-2 mb-6 border-b border-gray-200 pb-2">
        {TABS.map(tab => {
          const Icon = tab.icon
          return (
            <button key={tab.id}
              className={'flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-t-lg border-b-2 transition-colors ' +
                (activeTab === tab.id ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700')}
              onClick={() => setActiveTab(tab.id)}>
              <Icon size={18} />
              {t('settings:' + tab.id)}
            </button>
          )
        })}
      </div>

      <div className="card">
        {activeTab === 'general' && (
          <div className="space-y-4">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">{t('settings:generalSettings')}</h3>
            <div className="grid grid-cols-2 gap-4">
              <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('settings:companyName')}</label>
                <input className="input-field" value={settings.company_name} onChange={e => update('company_name', e.target.value)} /></div>
              <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('settings:companyEmail')}</label>
                <input type="email" className="input-field" value={settings.company_email} onChange={e => update('company_email', e.target.value)} /></div>
              <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('settings:companyPhone')}</label>
                <input className="input-field" value={settings.company_phone} onChange={e => update('company_phone', e.target.value)} /></div>
              <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('settings:currency')}</label>
                <select className="select-field" value={settings.currency} onChange={e => update('currency', e.target.value)}>
                  <option value="EGP">{t('settings:currencyEgp')}</option>
                  <option value="EUR">{t('settings:currencyEur')}</option>
                  <option value="GBP">{t('settings:currencyGbp')}</option>
                  <option value="NGN">{t('settings:currencyNgn')}</option>
                  <option value="KES">{t('settings:currencyKes')}</option>
                  <option value="ZAR">{t('settings:currencyZar')}</option>
                </select></div>
              <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('settings:timezone')}</label>
                <select className="select-field" value={settings.timezone} onChange={e => update('timezone', e.target.value)}>
                  <option value="America/New_York">{t('settings:timezoneEastern')}</option>
                  <option value="America/Chicago">{t('settings:timezoneCentral')}</option>
                  <option value="America/Denver">{t('settings:timezoneMountain')}</option>
                  <option value="America/Los_Angeles">{t('settings:timezonePacific')}</option>
                  <option value="UTC">{t('settings:timezoneUtc')}</option>
                </select></div>
              <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('settings:defaultPaymentTerms')}</label>
                <select className="select-field" value={settings.default_payment_terms} onChange={e => update('default_payment_terms', e.target.value)}>
                  <option value="net15">{t('settings:termsNet15')}</option>
                  <option value="net30">{t('settings:termsNet30')}</option>
                  <option value="net60">{t('settings:termsNet60')}</option>
                  <option value="cod">{t('settings:termsCod')}</option>
                </select></div>
              <div className="col-span-2"><label className="block text-sm font-medium text-gray-700 mb-1">{t('settings:companyAddress')}</label>
                <textarea className="input-field" rows={2} value={settings.company_address} onChange={e => update('company_address', e.target.value)} /></div>
            </div>
            <div className="grid grid-cols-2 gap-4 pt-4 border-t">
              <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('settings:lowStockThreshold')}</label>
                <input type="number" className="input-field" value={settings.low_stock_threshold}
                  onChange={e => update('low_stock_threshold', parseInt(e.target.value) || 0)} /></div>
              <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('settings:expiryAlertDays')}</label>
                <input type="number" className="input-field" value={settings.expiry_alert_days}
                  onChange={e => update('expiry_alert_days', parseInt(e.target.value) || 0)} /></div>
            </div>
          </div>
        )}

        {activeTab === 'notifications' && (
          <div className="space-y-4">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">{t('settings:notificationPreferences')}</h3>
            <div className="space-y-3">
              <label className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div><p className="font-medium text-gray-900">{t('settings:emailNotifications')}</p><p className="text-sm text-gray-500">{t('settings:emailNotificationsDesc')}</p></div>
                <input type="checkbox" className="w-5 h-5 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                  checked={settings.email_notifications} onChange={e => update('email_notifications', e.target.checked)} />
              </label>
              <label className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div><p className="font-medium text-gray-900">{t('settings:smsNotifications')}</p><p className="text-sm text-gray-500">{t('settings:smsNotificationsDesc')}</p></div>
                <input type="checkbox" className="w-5 h-5 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                  checked={settings.sms_notifications} onChange={e => update('sms_notifications', e.target.checked)} />
              </label>
            </div>
          </div>
        )}

        {activeTab === 'security' && (
          <div className="space-y-4">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">{t('settings:securitySettings')}</h3>
            <div className="space-y-3">
              <label className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div><p className="font-medium text-gray-900">{t('settings:requireOrderApproval')}</p><p className="text-sm text-gray-500">{t('settings:requireOrderApprovalDesc')}</p></div>
                <input type="checkbox" className="w-5 h-5 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                  checked={settings.require_order_approval} onChange={e => update('require_order_approval', e.target.checked)} />
              </label>
              <label className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div><p className="font-medium text-gray-900">{t('settings:autoGeneratePicklists')}</p><p className="text-sm text-gray-500">{t('settings:autoGeneratePicklistsDesc')}</p></div>
                <input type="checkbox" className="w-5 h-5 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                  checked={settings.auto_generate_picklists} onChange={e => update('auto_generate_picklists', e.target.checked)} />
              </label>
            </div>
          </div>
        )}

        {activeTab === 'appearance' && (
          <div className="space-y-4">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">{t('settings:appearance')}</h3>
            <p className="text-sm text-gray-500">{t('settings:themeComingSoon')}</p>
            <div className="flex items-center gap-4 p-6 bg-gray-50 rounded-lg justify-center">
              <Palette size={48} className="text-gray-300" />
              <div>
                <p className="font-medium">{t('settings:themeOptions')}</p>
                <p className="text-sm text-gray-500">{t('settings:themeDescription')}</p>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
