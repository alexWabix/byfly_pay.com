<template>
  <div class="sources-page">
    <div class="card">
      <div class="card-header flex justify-between items-center">
        <h3 class="card-title">Источники API</h3>
        <button @click="showCreateModal = true" class="btn btn-primary">
          + Добавить источник
        </button>
      </div>

      <div class="table-container">
        <table class="table">
          <thead>
            <tr>
              <th>Название</th>
              <th>Тип</th>
              <th>API Токен</th>
              <th>Статус</th>
              <th>Действия</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="source in sources" :key="source.id">
              <td><strong>{{ source.name }}</strong></td>
              <td>{{ getTypeText(source.type) }}</td>
              <td>
                <code class="token-display">{{ source.api_token }}</code>
                <button @click="copyToken(source.api_token)" class="btn-copy">📋</button>
              </td>
              <td>
                <span :class="'badge badge-' + (source.is_active ? 'success' : 'secondary')">
                  {{ source.is_active ? 'Активен' : 'Неактивен' }}
                </span>
              </td>
              <td>
                <button @click="editSource(source)" class="btn btn-sm btn-outline">
                  Изменить
                </button>
              </td>
            </tr>
            <tr v-if="!sources.length">
              <td colspan="5" class="text-center">Нет источников</td>
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
            {{ showEditModal ? 'Редактировать источник' : 'Добавить источник' }}
          </h3>
          <button @click="closeModals" class="btn-close">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Название *</label>
            <input v-model="form.name" type="text" class="form-control" />
          </div>

          <div class="form-group">
            <label class="form-label">Тип</label>
            <select v-model="form.type" class="form-select">
              <option value="website">Веб-сайт</option>
              <option value="mobile_app">Мобильное приложение</option>
              <option value="desktop_app">Десктоп приложение</option>
              <option value="other">Другое</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Описание</label>
            <textarea v-model="form.description" class="form-control" rows="3"></textarea>
          </div>

          <div class="form-group">
            <label class="form-label">
              <input v-model="form.is_active" type="checkbox" />
              Активен
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="closeModals" class="btn btn-outline">Отмена</button>
          <button @click="saveSource" class="btn btn-primary" :disabled="!form.name">
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

const sources = ref([])
const showCreateModal = ref(false)
const showEditModal = ref(false)
const form = ref({
  name: '',
  type: 'website',
  description: '',
  is_active: true
})
const editingId = ref(null)

onMounted(() => {
  loadSources()
})

async function loadSources() {
  try {
    const response = await api.get('/admin/sources')
    sources.value = response.data || []
  } catch (error) {
    console.error('Failed to load sources:', error)
  }
}

function editSource(source) {
  form.value = {
    name: source.name,
    type: source.type,
    description: source.description || '',
    is_active: Boolean(source.is_active)
  }
  editingId.value = source.id
  showEditModal.value = true
}

async function saveSource() {
  try {
    if (showEditModal.value) {
      await api.put(`/admin/sources/${editingId.value}`, form.value)
    } else {
      await api.post('/admin/sources', form.value)
    }
    await loadSources()
    closeModals()
  } catch (error) {
    alert(error.response?.data?.message || 'Ошибка сохранения')
  }
}

function closeModals() {
  showCreateModal.value = false
  showEditModal.value = false
  form.value = {
    name: '',
    type: 'website',
    description: '',
    is_active: true
  }
  editingId.value = null
}

function copyToken(token) {
  navigator.clipboard.writeText(token)
  alert('Токен скопирован в буфер обмена')
}

function getTypeText(type) {
  const types = {
    'website': 'Веб-сайт',
    'mobile_app': 'Мобильное приложение',
    'desktop_app': 'Десктоп приложение',
    'other': 'Другое'
  }
  return types[type] || type
}
</script>

<style scoped>
.token-display {
  font-size: 0.75rem;
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
  display: inline-block;
}

.btn-copy {
  border: none;
  background: none;
  cursor: pointer;
  margin-left: 0.5rem;
  font-size: 1rem;
}

.btn-close {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: var(--text-light);
}

.btn-sm {
  padding: 0.375rem 0.75rem;
  font-size: 0.75rem;
}

textarea.form-control {
  resize: vertical;
}
</style>

