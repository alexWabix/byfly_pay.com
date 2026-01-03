<template>
  <div class="phone-input">
    <div class="form-label" v-if="label">{{ label }}</div>
    <div class="phone-input-wrapper">
      <select 
        v-model="selectedCountry" 
        class="country-select"
        @change="handleCountryChange"
      >
        <option 
          v-for="country in countries" 
          :key="country.code + country.name"
          :value="country"
        >
          {{ country.flag }} {{ country.code }}
        </option>
      </select>
      <input
        ref="inputRef"
        type="tel"
        v-model="displayValue"
        @input="handleInput"
        @keydown="handleKeydown"
        @blur="$emit('blur')"
        class="form-control phone-number-input"
        :placeholder="getPlaceholder()"
        :disabled="disabled"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue'
import { countries } from '@/utils/phoneMask'

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  label: {
    type: String,
    default: ''
  },
  disabled: {
    type: Boolean,
    default: false
  },
  defaultCountry: {
    type: String,
    default: 'Казахстан'
  }
})

const emit = defineEmits(['update:modelValue', 'blur'])

const selectedCountry = ref(countries.find(c => c.name === props.defaultCountry) || countries[0])
const displayValue = ref('')
const inputRef = ref(null)
const isDeleting = ref(false)

// Initialize phone value
if (props.modelValue) {
  const withoutCode = props.modelValue.replace(selectedCountry.value.code, '')
  displayValue.value = withoutCode.replace(/\D/g, '')
}

function getPlaceholder() {
  // Убираем код страны из маски для placeholder
  const mask = selectedCountry.value.mask
  return mask.replace(selectedCountry.value.code, '').trim()
}

function handleCountryChange() {
  emitValue()
}

function handleKeydown(e) {
  // Определяем, была ли нажата клавиша удаления
  if (e.key === 'Backspace' || e.key === 'Delete') {
    isDeleting.value = true
  } else {
    isDeleting.value = false
  }
}

function handleInput(e) {
  let value = e.target.value
  const cursorPosition = e.target.selectionStart
  
  // Удаляем все нецифровые символы
  const cleaned = value.replace(/\D/g, '')
  
  // Ограничиваем длину в зависимости от страны
  let maxLength = 10 // По умолчанию для +7
  if (selectedCountry.value.code === '+998') maxLength = 9
  if (selectedCountry.value.code === '+994') maxLength = 9
  if (selectedCountry.value.code === '+996') maxLength = 9
  if (selectedCountry.value.code === '+374') maxLength = 8
  if (selectedCountry.value.code === '+995') maxLength = 9
  if (selectedCountry.value.code === '+375') maxLength = 9
  
  const truncated = cleaned.substring(0, maxLength)
  
  // Применяем форматирование
  const formatted = formatNumber(truncated)
  
  // Обновляем значение
  displayValue.value = formatted
  
  // Восстанавливаем позицию курсора
  nextTick(() => {
    if (inputRef.value && !isDeleting.value) {
      const newPosition = calculateCursorPosition(formatted, cursorPosition, value.length < formatted.length)
      inputRef.value.setSelectionRange(newPosition, newPosition)
    }
  })
  
  emitValue()
}

function formatNumber(number) {
  if (!number) return ''
  
  const code = selectedCountry.value.code
  let formatted = ''
  
  switch (code) {
    case '+7': // Казахстан, Россия
      if (number.length > 0) formatted += number.substring(0, 3)
      if (number.length > 3) formatted += ' ' + number.substring(3, 6)
      if (number.length > 6) formatted += ' ' + number.substring(6, 8)
      if (number.length > 8) formatted += ' ' + number.substring(8, 10)
      break
      
    case '+998': // Узбекистан
      if (number.length > 0) formatted += number.substring(0, 2)
      if (number.length > 2) formatted += ' ' + number.substring(2, 5)
      if (number.length > 5) formatted += ' ' + number.substring(5, 7)
      if (number.length > 7) formatted += ' ' + number.substring(7, 9)
      break
      
    case '+994': // Азербайджан
    case '+375': // Беларусь
      if (number.length > 0) formatted += number.substring(0, 2)
      if (number.length > 2) formatted += ' ' + number.substring(2, 5)
      if (number.length > 5) formatted += ' ' + number.substring(5, 7)
      if (number.length > 7) formatted += ' ' + number.substring(7, 9)
      break
      
    case '+996': // Кыргызстан
    case '+995': // Грузия
      if (number.length > 0) formatted += number.substring(0, 3)
      if (number.length > 3) formatted += ' ' + number.substring(3, 6)
      if (number.length > 6) formatted += ' ' + number.substring(6, 9)
      break
      
    case '+374': // Армения
      if (number.length > 0) formatted += number.substring(0, 2)
      if (number.length > 2) formatted += ' ' + number.substring(2, 5)
      if (number.length > 5) formatted += ' ' + number.substring(5, 8)
      break
      
    default:
      formatted = number
  }
  
  return formatted
}

function calculateCursorPosition(formatted, oldPosition, wasAdded) {
  // Если был добавлен символ форматирования, сдвигаем курсор
  if (wasAdded) {
    return oldPosition + 1
  }
  return oldPosition
}

function emitValue() {
  const cleaned = displayValue.value.replace(/\D/g, '')
  const fullPhone = cleaned ? selectedCountry.value.code + cleaned : ''
  emit('update:modelValue', fullPhone)
}

watch(() => props.modelValue, (newValue) => {
  if (!newValue) {
    displayValue.value = ''
  } else {
    const withoutCode = newValue.replace(selectedCountry.value.code, '')
    const cleaned = withoutCode.replace(/\D/g, '')
    if (cleaned !== displayValue.value.replace(/\D/g, '')) {
      displayValue.value = formatNumber(cleaned)
    }
  }
})
</script>

<style scoped>
.phone-input-wrapper {
  display: flex;
  gap: 0.5rem;
}

.country-select {
  width: 120px;
  padding: 0.625rem 0.5rem;
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  font-size: 0.875rem;
  background-color: white;
  cursor: pointer;
}

.phone-number-input {
  flex: 1;
}

.country-select:focus {
  outline: none;
  border-color: var(--primary-color);
}
</style>

