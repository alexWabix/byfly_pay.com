<template>
  <div class="create-payment-page">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">➕ Создать платеж на согласование</h3>
      </div>

      <form @submit.prevent="createPayment" class="payment-form">
        <div class="form-group">
          <label class="form-label">Категория *</label>
          <select v-model="form.category_id" class="form-control" required>
            <option :value="null">Выберите категорию</option>
            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
              {{ cat.icon_emoji }} {{ cat.name }}
            </option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Название платежа *</label>
          <input v-model="form.title" class="form-control" required placeholder="Например: Оплата туроператору Anex" />
        </div>

        <div class="form-group">
          <label class="form-label">Сумма *</label>
          <input v-model="form.amount" type="number" class="form-control" required placeholder="50000" />
        </div>

        <div class="form-group">
          <label class="form-label">Получатель</label>
          <input v-model="form.recipient" class="form-control" placeholder="ООО 'Анекс Тур'" />
        </div>

        <div class="form-group">
          <label class="form-label">Описание</label>
          <textarea v-model="form.description" class="form-control" rows="3" placeholder="Дополнительная информация"></textarea>
        </div>

        <div class="form-group">
          <label class="form-label">Подписанты *</label>
          <div class="approvers-list">
            <label v-for="admin in admins" :key="admin.id" class="approver-item">
              <input v-model="form.approver_ids" type="checkbox" :value="admin.id" />
              <span>{{ admin.name || admin.phone }} ({{ admin.phone }})</span>
            </label>
          </div>
          <small class="form-hint">Выбрано: {{ form.approver_ids.length }}</small>
        </div>

        <div class="form-group">
          <label class="form-label">Документ (основание)</label>
          <input type="file" @change="uploadDocument" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" />
          <small v-if="form.document_filename" class="form-hint">📎 {{ form.document_filename }}</small>
        </div>

        <div class="form-actions">
          <router-link to="/approval-payments" class="btn btn-outline">Отмена</router-link>
          <button type="submit" class="btn btn-primary" :disabled="creating || form.approver_ids.length === 0">
            <span v-if="creating">Создание...</span>
            <span v-else">Отправить на согласование</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'

const router = useRouter()
const categories = ref([])
const admins = ref([])
const creating = ref(false)

const form = ref({
  category_id: null,
  title: '',
  amount: '',
  recipient: '',
  description: '',
  approver_ids: [],
  document_url: null,
  document_filename: null
})

onMounted(async () => {
  await loadCategories()
  await loadAdmins()
})

async function loadCategories() {
  try {
    const response = await api.get('/approvals/categories')
    categories.value = response.data || []
  } catch (error) {
    console.error(error)
  }
}

async function loadAdmins() {
  try {
    const response = await api.get('/admin/admins')
    admins.value = response.data || []
  } catch (error) {
    console.error(error)
  }
}

async function uploadDocument(event) {
  const file = event.target.files[0]
  if (!file) return
  
  const formData = new FormData()
  formData.append('file', file)
  
  try {
    const response = await api.post('/upload/document', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    
    form.value.document_url = response.data.url
    form.value.document_filename = response.data.filename
    alert('✅ Документ загружен успешно!')
  } catch (error) {
    console.error(error)
    alert('❌ Ошибка загрузки документа')
  }
}

async function createPayment() {
  if (form.value.approver_ids.length === 0) {
    alert('Выберите хотя бы одного подписанта')
    return
  }
  
  creating.value = true
  
  try {
    await api.post('/approvals/payments', form.value)
    alert('✅ Платеж отправлен на согласование!')
    router.push('/approval-payments')
  } catch (error) {
    alert(error.response?.data?.message || 'Ошибка создания')
  } finally {
    creating.value = false
  }
}
</script>

<style scoped>
.payment-form {
  padding: 2rem;
  max-width: 800px;
}

.form-actions {
  display: flex;
  gap: 1rem;
  margin-top: 2rem;
}

.approvers-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 0.75rem;
  padding: 1rem;
  background: var(--bg-color);
  border-radius: 0.75rem;
}

.approver-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem;
  background: white;
  border-radius: 0.5rem;
  cursor: pointer;
  transition: all 0.2s;
}

.approver-item:hover {
  background: rgba(102, 126, 234, 0.1);
}

.approver-item input[type="checkbox"] {
  width: 20px;
  height: 20px;
}
</style>

