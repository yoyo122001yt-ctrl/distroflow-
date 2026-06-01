export function validateEmail(email) {
  if (!email) return 'Email is required'
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return re.test(email) ? null : 'Invalid email address'
}

export function validatePhone(phone) {
  if (!phone) return null
  const cleaned = phone.replace(/\D/g, '')
  if (cleaned.length >= 10 && cleaned.length <= 11) return null
  return 'Invalid phone number'
}

export function validateRequired(value, fieldName = 'This field') {
  if (value === null || value === undefined) return `${fieldName} is required`
  if (typeof value === 'string' && !value.trim()) return `${fieldName} is required`
  return null
}

export function validateMinLength(value, min, fieldName = 'This field') {
  const err = validateRequired(value, fieldName)
  if (err) return err
  if (typeof value === 'string' && value.trim().length < min) {
    return `${fieldName} must be at least ${min} characters`
  }
  return null
}

export function validatePositiveNumber(value, fieldName = 'This field') {
  const num = Number(value)
  if (isNaN(num) || num <= 0) return `${fieldName} must be a positive number`
  return null
}
