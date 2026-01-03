<template>
  <div class="admins-page">
    <div class="card">
      <div class="card-header flex justify-between items-center">
        <h3 class="card-title">Администраторы</h3>
        <button @click="showCreateModal = true" class="btn btn-primary">
          + Добавить администратора
        </button>
      </div>

      <div class="table-container">
        <table class="table">
          <thead>
            <tr>
              <th>Телефон</th>
              <th>Имя</th>
              <th>Страна</th>
              <th>Супер-админ</th>
              <th>Статус</th>
              <th>Последний вход</th>
              <th>Действия</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="admin in admins" :key="admin.id">
              <td><strong>{{ admin.phone }}</strong></td>
              <td>{{ admin.name || '-' }}</td>
              <td>{{ admin.country_name || '-' }}</td>
              <td>
                <span v-if="admin.is_super_admin" class="badge badge-warning">Да</span>
                <span v-else>-</span>
              </td>
              <td>
                <span :class="'badge badge-' + (admin.is_active ? 'success' : 'secondary')">
                  {{ admin.is_active ? 'Активен' : 'Неактивен' }}
                </span>
              </td>
              <td>{{ admin.last_login ? formatDate(admin.last_login) : 'Никогда' }}</td>
              <td>
                <button @click="editAdmin(admin)" class="btn btn-sm btn-outline">
                  Изменить
                </button>
              </td>
            </tr>
            <tr v-if="!admins.length">
              <td colspan="7" class="text-center">Нет администраторов</td>
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
            {{ showEditModal ? 'Редактировать администратора' : 'Добавить администратора' }}
          </h3>
          <button @click="closeModals" class="btn-close">✕</button>
        </div>
        <div class="modal-body">
          <PhoneInput
            v-model="form.phone"
            label="Номер телефона *"
            :default-country="form.country_name || 'Казахстан'"
          />
          <small v-if="showEditModal" class="form-hint warning">⚠️ Изменение телефона потребует повторной авторизации администратора</small>

          <div class="form-group">
            <label class="form-label">Имя</label>
            <input v-model="form.name" type="text" class="form-control" />
          </div>

          <div class="form-group">
            <label class="form-label">Страна</label>
            <select v-model="form.country_name" class="form-select">
              <option value="Казахстан">Казахстан</option>
              <option value="Узбекистан">Узбекистан</option>
              <option value="Азербайджан">Азербайджан</option>
              <option value="Россия">Россия</option>
              <option value="Кыргызстан">Кыргызстан</option>
              <option value="Армения">Армения</option>
              <option value="Грузия">Грузия</option>
              <option value="Белоруссия">Белоруссия</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">
              <input v-model="form.is_super_admin" type="checkbox" />
              Супер-администратор
            </label>
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
          <button @click="saveAdmin" class="btn btn-primary" :disabled="!showEditModal && !form.phone">
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
import PhoneInput from '@/components/PhoneInput.vue'

const admins = ref([])
const showCreateModal = ref(false)
const showEditModal = ref(false)
const form = ref({
  phone: '',
  name: '',
  country_name: 'Казахстан',
  is_super_admin: false,
  is_active: true
})
const editingId = ref(null)

onMounted(() => {
  loadAdmins()
})

async function loadAdmins() {
  try {
    const response = await api.get('/admin/admins')
    admins.value = response.data || []
  } catch (error) {
    console.error('Failed to load admins:', error)
  }
}

function editAdmin(admin) {
  form.value = {
    phone: admin.phone || '',
    name: admin.name || '',
    country_name: admin.country_name || 'Казахстан',
    is_super_admin: Boolean(admin.is_super_admin),
    is_active: Boolean(admin.is_active)
  }
  editingId.value = admin.id
  showEditModal.value = true
}

async function saveAdmin() {
  if (!form.value.phone) {
    alert('Номер телефона обязателен')
    return
  }
  
  try {
    if (showEditModal.value) {
      await api.put(`/admin/admins/${editingId.value}`, form.value)
      alert('✅ Администратор обновлен')
    } else {
      await api.post('/admin/admins', form.value)
      alert('✅ Администратор добавлен')
    }
    await loadAdmins()
    closeModals()
  } catch (error) {
    alert(error.response?.data?.message || 'Ошибка сохранения')
  }
}

function closeModals() {
  showCreateModal.value = false
  showEditModal.value = false
  form.value = {
    phone: '',
    name: '',
    country_name: 'Казахстан',
    is_super_admin: false,
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
</script>

<style scoped>
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
</style>

