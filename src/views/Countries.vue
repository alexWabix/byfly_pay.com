<template>
  <div class="countries-page">
    <div class="card">
      <div class="card-header flex justify-between items-center">
        <h3 class="card-title">Управление странами</h3>
        <button @click="showCreateModal = true" class="btn btn-primary">
          + Добавить страну
        </button>
      </div>

      <div class="table-container">
        <table class="table">
          <thead>
            <tr>
              <th>Флаг</th>
              <th>Название</th>
              <th>Код</th>
              <th>Валюта</th>
              <th>Телефонный код</th>
              <th>Статус</th>
              <th>Действия</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="country in countries" :key="country.id">
              <td><span class="country-flag-large">{{ country.flag_emoji }}</span></td>
              <td><strong>{{ country.name}}</strong><br><small>{{ country.name_en }}</small></td>
              <td><code>{{ country.code }}</code></td>
              <td>{{ country.currency_code }} {{ country.currency_symbol }}</td>
              <td>{{ country.phone_code }}</td>
              <td>
                <span :class="'badge badge-' + (country.is_active ? 'success' : 'secondary')">
                  {{ country.is_active ? 'Активна' : 'Неактивна' }}
                </span>
              </td>
              <td>
                <div class="action-buttons">
                  <button @click="editCountry(country)" class="btn btn-sm btn-outline">
                    Изменить
                  </button>
                  <button @click="deleteCountry(country.id)" class="btn btn-sm btn-danger">
                    Удалить
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!countries.length">
              <td colspan="7" class="text-center">Нет стран</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showCreateModal || showEditModal" class="modal-overlay" @click.self="closeModals">
      <div class="modal">
        <div class="modal-header">
          <h3 class="modal-title">
            {{ showEditModal ? 'Редактировать страну' : 'Добавить страну' }}
          </h3>
          <button @click="closeModals" class="btn-close">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Код страны *</label>
            <input v-model="form.code" type="text" class="form-control" placeholder="KZ" maxlength="10" />
            <small class="form-hint">ISO код страны (2-3 символа)</small>
          </div>

          <div class="form-group">
            <label class="form-label">Название *</label>
            <input v-model="form.name" type="text" class="form-control" placeholder="Казахстан" />
          </div>

          <div class="form-group">
            <label class="form-label">Название (EN)</label>
            <input v-model="form.name_en" type="text" class="form-control" placeholder="Kazakhstan" />
          </div>

          <div class="form-group">
            <label class="form-label">Код валюты *</label>
            <input v-model="form.currency_code" type="text" class="form-control" placeholder="KZT" maxlength="10" />
          </div>

          <div class="form-group">
            <label class="form-label">Символ валюты *</label>
            <input v-model="form.currency_symbol" type="text" class="form-control" placeholder="₸" maxlength="10" />
          </div>

          <div class="form-group">
            <label class="form-label">Телефонный код *</label>
            <input v-model="form.phone_code" type="text" class="form-control" placeholder="+7" />
          </div>

          <div class="form-group">
            <label class="form-label">Маска телефона</label>
            <input v-model="form.phone_mask" type="text" class="form-control" placeholder="+7 (###) ###-##-##" />
          </div>

          <div class="form-group">
            <label class="form-label">Флаг (эмодзи)</label>
            <input v-model="form.flag_emoji" type="text" class="form-control" placeholder="🇰🇿" maxlength="10" />
          </div>

          <div class="form-group">
            <label class="form-label">
              <input v-model="form.is_active" type="checkbox" />
              Активна
            </label>
          </div>

          <div class="form-group">
            <label class="form-label">Порядок сортировки</label>
            <input v-model.number="form.sort_order" type="number" class="form-control" placeholder="0" />
          </div>
        </div>
        <div class="modal-footer">
          <button @click="closeModals" class="btn btn-outline">Отмена</button>
          <button @click="saveCountry" class="btn btn-primary" :disabled="!form.code || !form.name">
            {{ showEditModal ? 'Сохранить' : 'Создать' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const countries = ref([])
const showCreateModal = ref(false)
const showEditModal = ref(false)
const form = ref({
  code: '',
  name: '',
  name_en: '',
  currency_code: '',
  currency_symbol: '',
  phone_code: '',
  phone_mask: '',
  flag_emoji: '',
  is_active: true,
  sort_order: 0
})
const editingId = ref(null)

onMounted(() => {
  loadCountries()
})

async function loadCountries() {
  try {
    const response = await api.get('/admin/countries')
    countries.value = response.data || []
  } catch (error) {
    console.error('Failed to load countries:', error)
  }
}

function editCountry(country) {
  form.value = {
    code: country.code,
    name: country.name,
    name_en: country.name_en || '',
    currency_code: country.currency_code || '',
    currency_symbol: country.currency_symbol || '',
    phone_code: country.phone_code || '',
    phone_mask: country.phone_mask || '',
    flag_emoji: country.flag_emoji || '',
    is_active: Boolean(country.is_active),
    sort_order: country.sort_order || 0
  }
  editingId.value = country.id
  showEditModal.value = true
}

async function saveCountry() {
  try {
    if (showEditModal.value) {
      await api.put(`/admin/countries/${editingId.value}`, form.value)
    } else {
      await api.post('/admin/countries', form.value)
    }
    await loadCountries()
    closeModals()
  } catch (error) {
    alert(error.response?.data?.message || 'Ошибка сохранения')
  }
}

async function deleteCountry(id) {
  if (!confirm('Вы уверены, что хотите удалить эту страну?')) {
    return
  }

  try {
    await api.delete(`/admin/countries/${id}`)
    await loadCountries()
  } catch (error) {
    alert(error.response?.data?.message || 'Ошибка удаления')
  }
}

function closeModals() {
  showCreateModal.value = false
  showEditModal.value = false
  form.value = {
    code: '',
    name: '',
    name_en: '',
    currency_code: '',
    currency_symbol: '',
    phone_code: '',
    phone_mask: '',
    flag_emoji: '',
    is_active: true,
    sort_order: 0
  }
  editingId.value = null
}
</script>

<style scoped>
.action-buttons {
  display: flex;
  gap: 0.5rem;
}

.country-flag-large {
  font-size: 2rem;
}

code {
  background: var(--bg-color);
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-family: monospace;
  font-size: 0.75rem;
}

small {
  color: var(--text-light);
}
</style>

