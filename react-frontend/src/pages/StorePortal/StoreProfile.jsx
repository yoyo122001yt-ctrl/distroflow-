import { useState, useEffect } from 'react';
import api from '../../services/api';

export default function StoreProfile() {
  const [profile, setProfile] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({});

  useEffect(() => {
    async function load() {
      try {
        const res = await api.get('/auth/me');
        const user = res.data?.data || res.data;
        setProfile(user);
        setForm({
          name: user.name || '',
          email: user.email || '',
          phone: user.phone || '',
          address: user.retail_store?.address || '',
          whatsapp_phone: user.retail_store?.whatsapp_phone || '',
          sms_phone: user.retail_store?.sms_phone || '',
        });
      } catch (err) {
        console.error('Failed to load profile:', err);
      } finally {
        setLoading(false);
      }
    }
    load();
  }, []);

  async function save() {
    setSaving(true);
    try {
      const payload = {
        name: form.name,
        email: form.email,
        phone: form.phone,
        retail_store: {
          address: form.address,
          whatsapp_phone: form.whatsapp_phone,
          sms_phone: form.sms_phone,
        },
      };
      await api.put('/auth/profile', payload);
      alert('Profile updated successfully');
    } catch (err) {
      alert('Failed to update profile: ' + (err.response?.data?.message || err.message));
    } finally {
      setSaving(false);
    }
  }

  if (loading) return <div className="p-6">Loading profile...</div>;

  return (
    <div className="p-6">
      <h1 className="text-2xl font-bold mb-6">Profile Settings</h1>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div className="border rounded-lg p-6">
          <h2 className="text-lg font-semibold mb-4">Account Information</h2>
          <div className="space-y-4">
            <div>
              <label className="block text-sm text-gray-600 mb-1">Name</label>
              <input
                type="text" value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                className="w-full border rounded-lg px-4 py-2"
              />
            </div>
            <div>
              <label className="block text-sm text-gray-600 mb-1">Email</label>
              <input
                type="email" value={form.email}
                onChange={(e) => setForm({ ...form, email: e.target.value })}
                className="w-full border rounded-lg px-4 py-2"
              />
            </div>
            <div>
              <label className="block text-sm text-gray-600 mb-1">Phone</label>
              <input
                type="text" value={form.phone}
                onChange={(e) => setForm({ ...form, phone: e.target.value })}
                className="w-full border rounded-lg px-4 py-2"
              />
            </div>
            <div>
              <label className="block text-sm text-gray-600 mb-1">Address</label>
              <textarea
                value={form.address}
                onChange={(e) => setForm({ ...form, address: e.target.value })}
                className="w-full border rounded-lg px-4 py-2"
                rows="3"
              />
            </div>
          </div>
        </div>

        <div className="border rounded-lg p-6">
          <h2 className="text-lg font-semibold mb-4">Notification Preferences</h2>
          <div className="space-y-4">
            <div>
              <label className="block text-sm text-gray-600 mb-1">WhatsApp Number</label>
              <input
                type="text" value={form.whatsapp_phone}
                onChange={(e) => setForm({ ...form, whatsapp_phone: e.target.value })}
                placeholder="+201234567890"
                className="w-full border rounded-lg px-4 py-2"
              />
            </div>
            <div>
              <label className="block text-sm text-gray-600 mb-1">SMS Number</label>
              <input
                type="text" value={form.sms_phone}
                onChange={(e) => setForm({ ...form, sms_phone: e.target.value })}
                placeholder="+201234567890"
                className="w-full border rounded-lg px-4 py-2"
              />
            </div>
          </div>
        </div>
      </div>

      <button
        onClick={save}
        disabled={saving}
        className="mt-6 bg-blue-600 text-white rounded-lg px-8 py-3 font-bold hover:bg-blue-700 disabled:opacity-50"
      >
        {saving ? 'Saving...' : 'Save Changes'}
      </button>
    </div>
  );
}
