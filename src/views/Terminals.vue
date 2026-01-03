<template>
  <div class="terminals-page">
    <div class="card">
      <div class="card-header flex justify-between items-center">
        <h3 class="card-title">Терминалы Kaspi</h3>
        <div class="header-actions">
          <button 
            @click="checkAllTerminals" 
            class="btn btn-success"
            :disabled="checkingAll"
          >
            <span v-if="checkingAll" class="checking-indicator">
              <span class="spinner-small"></span>
              <span>Проверка...</span>
            </span>
            <span v-else>🔄 Проверить все</span>
          </button>
          <button @click="showCreateModal = true" class="btn btn-primary">
            + Добавить терминал
          </button>
        </div>
      </div>

      <div class="table-container">
        <table class="table">
          <thead>
            <tr>
              <th>Название</th>
              <th>IP адрес</th>
              <th>Порт</th>
              <th>Камера</th>
              <th>Статус</th>
              <th>Состояние</th>
              <th>Последнее использование</th>
              <th>Действия</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="terminal in terminals" :key="terminal.id">
              <td><strong>{{ terminal.name }}</strong></td>
              <td>{{ terminal.ip_address }}</td>
              <td>{{ terminal.port }}</td>
              <td>
                <span v-if="terminal.camera_id">Камера #{{ terminal.camera_id }}</span>
                <span v-else class="text-light">-</span>
              </td>
              <td>
                <span :class="'badge badge-' + getStatusClass(terminal.status)">
                  {{ getStatusText(terminal.status) }}
                </span>
              </td>
              <td>
                <span :class="'badge badge-' + (terminal.is_busy ? 'warning' : 'success')">
                  {{ terminal.is_busy ? 'Занят' : 'Свободен' }}
                </span>
              </td>
              <td>{{ terminal.last_used ? formatDate(terminal.last_used) : 'Никогда' }}</td>
              <td>
                <div class="action-buttons">
                  <a 
                    :href="`http://109.175.215.40:3000/capture/${terminal.camera_id}`" 
                    target="_blank"
                    class="btn btn-sm btn-success" 
                    title="Посмотреть камеру"
                    v-if="terminal.camera_id"
                  >
                    📷
                  </a>
                  <button 
                    @click="checkStatus(terminal.id)" 
                    class="btn btn-sm" 
                    :class="checkingStatus[terminal.id] ? 'btn-primary' : 'btn-outline'"
                    title="Проверить статус"
                    :disabled="checkingStatus[terminal.id]"
                  >
                    <span v-if="checkingStatus[terminal.id]" class="checking-indicator">
                      <span class="spinner-small"></span>
                      <span class="checking-text">Проверка...</span>
                    </span>
                    <span v-else>🔄</span>
                  </button>
                  <button @click="editTerminal(terminal)" class="btn btn-sm btn-outline">
                    Изменить
                  </button>
                  <button @click="deleteTerminal(terminal.id)" class="btn btn-sm btn-danger">
                    Удалить
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!terminals.length">
              <td colspan="8" class="text-center">Нет терминалов</td>
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
            {{ showEditModal ? 'Редактировать терминал' : 'Добавить терминал' }}
          </h3>
          <button @click="closeModals" class="btn-close">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Название</label>
            <input v-model="form.name" type="text" class="form-control" placeholder="Терминал 1" />
          </div>

          <div class="form-group">
            <label class="form-label">IP адрес *</label>
            <input v-model="form.ip_address" type="text" class="form-control" placeholder="https://109.175.215.40" />
            <small class="form-hint">Используйте HTTPS согласно документации Kaspi</small>
          </div>

          <div class="form-group">
            <label class="form-label">Порт *</label>
            <input v-model.number="form.port" type="number" class="form-control" placeholder="8080" />
            <small class="form-hint">Согласно документации Kaspi: 8080</small>
          </div>

          <div class="form-group">
            <label class="form-label">ID камеры</label>
            <input v-model.number="form.camera_id" type="number" class="form-control" placeholder="1" />
          </div>

          <div class="form-group">
            <label class="form-label">URL камеры</label>
            <input v-model="form.camera_url" type="text" class="form-control" placeholder="http://109.175.215.40:3000/scan-qr/1" />
          </div>

          <div class="form-group">
            <label class="form-label">Access Token</label>
            <input v-model="form.access_token" type="text" class="form-control" placeholder="Токен для авторизации на терминале" />
            <small class="form-hint">Получите токен на терминале (Панель администратора)</small>
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
          <button @click="saveTerminal" class="btn btn-primary" :disabled="!form.ip_address || !form.port">
            {{ showEditModal ? 'Сохранить' : 'Создать' }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import api from '@/services/api'

const terminals = ref([])
const showCreateModal = ref(false)
const showEditModal = ref(false)
const checkingAll = ref(false)
const autoRefreshInterval = ref(null)
const form = ref({
  name: '',
  ip_address: '',
  port: 8080,
  access_token: '',
  camera_id: null,
  camera_url: '',
  is_active: true
})
const editingId = ref(null)

onMounted(() => {
  loadTerminals()
  startAutoRefresh()
})

onUnmounted(() => {
  stopAutoRefresh()
})

function startAutoRefresh() {
  // Обновляем данные каждые 2 секунды
  autoRefreshInterval.value = setInterval(() => {
    loadTerminals()
  }, 2000)
}

function stopAutoRefresh() {
  if (autoRefreshInterval.value) {
    clearInterval(autoRefreshInterval.value)
  }
}

async function loadTerminals() {
  try {
    const response = await api.get('/admin/terminals')
    terminals.value = response.data || []
  } catch (error) {
    console.error('Failed to load terminals:', error)
  }
}

function editTerminal(terminal) {
  form.value = {
    name: terminal.name || '',
    ip_address: terminal.ip_address,
    port: terminal.port || 8080,
    access_token: terminal.access_token || '',
    camera_id: terminal.camera_id,
    camera_url: terminal.camera_url || '',
    is_active: Boolean(terminal.is_active)
  }
  editingId.value = terminal.id
  showEditModal.value = true
}

async function saveTerminal() {
  try {
    if (showEditModal.value) {
      await api.put(`/admin/terminals/${editingId.value}`, form.value)
    } else {
      await api.post('/admin/terminals', form.value)
    }
    await loadTerminals()
    closeModals()
  } catch (error) {
    alert(error.response?.data?.message || 'Ошибка сохранения')
  }
}

async function deleteTerminal(id) {
  if (!confirm('Вы уверены, что хотите удалить этот терминал?')) {
    return
  }

  try {
    await api.delete(`/admin/terminals/${id}`)
    await loadTerminals()
  } catch (error) {
    alert(error.response?.data?.message || 'Ошибка удаления')
  }
}

const checkingStatus = ref({})

async function checkStatus(id) {
  checkingStatus.value[id] = true
  
  try {
    const response = await api.get(`/admin/terminals/${id}/status`)
    
    const status = response.data.status
    const message = response.data.message
    
    if (status === 'online') {
      alert(`✅ Терминал доступен\n\n${message}`)
    } else {
      alert(`❌ Терминал недоступен\n\n${message}`)
    }
    
    await loadTerminals()
  } catch (error) {
    alert('Ошибка проверки статуса: ' + (error.response?.data?.message || error.message))
  } finally {
    checkingStatus.value[id] = false
  }
}

async function checkAllTerminals() {
  if (!confirm('Проверить все терминалы? Это может занять некоторое время.')) {
    return
  }
  
  checkingAll.value = true
  
  try {
    const response = await api.post('/admin/terminals/check-all')
    
    const results = response.data.results || []
    const onlineCount = response.data.online || 0
    const offlineCount = response.data.offline || 0
    
    let message = `Проверка завершена!\n\n`
    message += `✅ Онлайн: ${onlineCount}\n`
    message += `❌ Офлайн: ${offlineCount}\n\n`
    
    if (offlineCount > 0) {
      message += `Проблемные терминалы:\n`
      results.filter(r => r.status !== 'online').forEach(r => {
        message += `• ${r.name}: ${r.message}\n`
      })
    }
    
    alert(message)
    
    await loadTerminals()
  } catch (error) {
    alert('Ошибка массовой проверки: ' + (error.response?.data?.message || error.message))
  } finally {
    checkingAll.value = false
  }
}

function closeModals() {
  showCreateModal.value = false
  showEditModal.value = false
  form.value = {
    name: '',
    ip_address: '',
    port: 8080,
    access_token: '',
    camera_id: null,
    camera_url: '',
    is_active: true
  }
  editingId.value = null
}

function formatDate(dateString) {
  return new Date(dateString).toLocaleString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function getStatusClass(status) {
  const classes = {
    'online': 'success',
    'offline': 'secondary',
    'error': 'danger'
  }
  return classes[status] || 'secondary'
}

function getStatusText(status) {
  const texts = {
    'online': 'Онлайн',
    'offline': 'Офлайн',
    'error': 'Ошибка'
  }
  return texts[status] || status
}
</script>

<style scoped>
.header-actions {
  display: flex;
  gap: 1rem;
}

.action-buttons {
  display: flex;
  gap: 0.5rem;
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

.text-light {
  color: var(--text-light);
}

.camera-modal {
  max-width: 800px;
}

.camera-display {
  padding: 0.5rem;
}

.camera-image-container {
  background: #000;
  border-radius: 1rem;
  overflow: hidden;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
  margin-bottom: 1.5rem;
  min-height: 400px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.camera-image {
  width: 100%;
  height: auto;
  display: block;
}

.camera-info-footer {
  display: flex;
  gap: 1rem;
  justify-content: center;
  flex-wrap: wrap;
}

.info-badge {
  padding: 0.75rem 1.25rem;
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
  border-radius: 2rem;
  font-size: 0.875rem;
  border: 1px solid rgba(102, 126, 234, 0.2);
}

.info-badge strong {
  color: var(--primary-color);
  margin-right: 0.5rem;
}

.camera-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem;
}

.spinner {
  width: 50px;
  height: 50px;
  border: 4px solid rgba(102, 126, 234, 0.2);
  border-top-color: var(--primary-color);
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>

