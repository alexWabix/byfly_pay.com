<template>
  <div class="categories-page">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">📁 Категории платежей</h3>
        <button @click="showCreateModal = true" class="btn btn-primary">➕ Создать</button>
      </div>
      
      <div class="categories-grid">
        <div v-for="category in categories" :key="category.id" class="category-card" :style="{ borderColor: category.color }">
          <div class="category-icon" :style="{ background: category.color }">
            {{ category.icon_emoji }}
          </div>
          <div class="category-info">
            <h4>{{ category.name }}</h4>
            <p>{{ category.description || 'Нет описания' }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showCreateModal" class="modal-overlay" @click.self="showCreateModal = false">
      <div class="modal">
        <div class="modal-header">
          <h3>Создать категорию</h3>
          <button @click="showCreateModal = false" class="btn-close">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Название *</label>
            <input v-model="form.name" class="form-control" />
          </div>
          <div class="form-group">
            <label>Описание</label>
            <textarea v-model="form.description" class="form-control" rows="2"></textarea>
          </div>
          <div class="form-group">
            <label>Иконка</label>
            <input v-model="form.icon_emoji" class="form-control" />
          </div>
          <div class="form-group">
            <label>Цвет</label>
            <input v-model="form.color" type="color" class="form-control" />
          </div>
        </div>
        <div class="modal-footer">
          <button @click="showCreateModal = false" class="btn btn-outline">Отмена</button>
          <button @click="saveCategory" class="btn btn-primary">Сохранить</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const categories = ref([])
const showCreateModal = ref(false)
const form = ref({ name: '', description: '', icon_emoji: '💰', color: '#667eea' })

onMounted(() => loadCategories())

async function loadCategories() {
  try {
    const response = await api.get('/approvals/categories')
    categories.value = response.data || []
  } catch (error) {
    console.error(error)
  }
}

async function saveCategory() {
  try {
    await api.post('/approvals/categories', form.value)
    showCreateModal.value = false
    form.value = { name: '', description: '', icon_emoji: '💰', color: '#667eea' }
    loadCategories()
  } catch (error) {
    alert('Ошибка')
  }
}
</script>

<style scoped>
.categories-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1.5rem;
  padding: 1.5rem;
}

.category-card {
  display: flex;
  gap: 1rem;
  padding: 1.5rem;
  background: white;
  border-radius: 1rem;
  border-left: 4px solid;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.category-icon {
  width: 60px;
  height: 60px;
  border-radius: 1rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  color: white;
}

.category-info {
  flex: 1;
}

.category-info h4 {
  margin: 0 0 0.5rem 0;
}

.category-info p {
  margin: 0;
  font-size: 0.875rem;
  color: var(--text-light);
}
</style>




