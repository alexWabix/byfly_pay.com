/**
 * Phone masking utilities
 */

export const countries = [
  { code: '+7', name: 'Казахстан', mask: '+7 (###) ### ## ##', flag: '🇰🇿' },
  { code: '+7', name: 'Россия', mask: '+7 (###) ### ## ##', flag: '🇷🇺' },
  { code: '+998', name: 'Узбекистан', mask: '+998 ## ### ## ##', flag: '🇺🇿' },
  { code: '+994', name: 'Азербайджан', mask: '+994 ## ### ## ##', flag: '🇦🇿' },
  { code: '+996', name: 'Кыргызстан', mask: '+996 ### ### ###', flag: '🇰🇬' },
  { code: '+374', name: 'Армения', mask: '+374 ## ### ###', flag: '🇦🇲' },
  { code: '+995', name: 'Грузия', mask: '+995 ### ### ###', flag: '🇬🇪' },
  { code: '+375', name: 'Белоруссия', mask: '+375 ## ### ## ##', flag: '🇧🇾' }
]

export function applyMask(value, mask) {
  if (!value || !mask) return value

  const cleanValue = value.replace(/\D/g, '')
  let result = ''
  let valueIndex = 0

  for (let i = 0; i < mask.length && valueIndex < cleanValue.length; i++) {
    if (mask[i] === '#') {
      result += cleanValue[valueIndex]
      valueIndex++
    } else {
      result += mask[i]
    }
  }

  return result
}

export function cleanPhone(phone) {
  return phone.replace(/\D/g, '')
}

export function formatPhone(phone, countryCode = '+7') {
  const country = countries.find(c => c.code === countryCode)
  if (!country) return phone

  const cleaned = cleanPhone(phone)
  return applyMask(cleaned, country.mask)
}

export function getCountryByCode(code) {
  return countries.find(c => c.code === code) || countries[0]
}

