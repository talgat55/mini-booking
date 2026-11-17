<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Skeleton from 'primevue/skeleton';
import { useBookings } from './composables/useBookings';

const { slots, timezone, loading, pending, loaded, loadError, serverOffset, load, create, cancel } = useBookings();
const selectedDate = ref('');
const activeSlot = ref(null);
const bookingDialog = ref(false);
const cancelDialog = ref(false);
const form = reactive({ customer_name: '', customer_email: '' });
const fieldErrors = ref({});
const actionError = ref('');
const success = ref('');
const clock = ref(Date.now());
let timer;
const dateKey = (value) => new Intl.DateTimeFormat('en-CA', { timeZone: timezone.value, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date(value));
const time = (value) => new Intl.DateTimeFormat('ru-RU', { timeZone: timezone.value, hour: '2-digit', minute: '2-digit' }).format(new Date(value));
const dateLabel = (value, options = {}) => new Intl.DateTimeFormat('ru-RU', { timeZone: timezone.value, day: 'numeric', month: 'long', ...options }).format(new Date(value));
const status = (slot) => Date.parse(slot.starts_at) <= clock.value + serverOffset.value ? 'past' : slot.status;
const dates = computed(() => [...new Set(slots.value.map(s => dateKey(s.starts_at)))].map(value => {
  const slot = slots.value.find(s => dateKey(s.starts_at) === value);
  return { value, label: dateLabel(slot.starts_at, { weekday: 'short' }), startsAt: slot.starts_at };
}));
const visibleSlots = computed(() => slots.value.filter(s => dateKey(s.starts_at) === selectedDate.value));
const availableCount = computed(() => visibleSlots.value.filter(s => status(s) === 'available').length);
const selectedDay = computed(() => dates.value.find(d => d.value === selectedDate.value));
const labels = { available: 'Свободно', booked: 'Занято', past: 'Прошло' };
const severities = { available: 'success', booked: 'warn', past: 'secondary' };
const isBusy = computed(() => pending.value || loading.value);

async function refresh() {
  const ok = await load();
  if (ok && !dates.value.some(d => d.value === selectedDate.value)) selectedDate.value = dates.value[0]?.value || '';
  clock.value = Date.now();
}
function openBooking(slot) {
  activeSlot.value = slot;
  form.customer_name = '';
  form.customer_email = '';
  fieldErrors.value = {};
  actionError.value = '';
  success.value = '';
  bookingDialog.value = true;
}
function openCancel(slot) {
  activeSlot.value = slot;
  actionError.value = '';
  success.value = '';
  cancelDialog.value = true;
}
async function submitBooking() {
  if (isBusy.value) return;
  fieldErrors.value = {};
  actionError.value = '';
  if (!form.customer_name.trim()) fieldErrors.value.customer_name = ['Введите имя.'];
  if (!form.customer_email.trim()) fieldErrors.value.customer_email = ['Введите email.'];
  if (Object.keys(fieldErrors.value).length) return;
  pending.value = true;
  try {
    await create({ slot_id: activeSlot.value.id, customer_name: form.customer_name.trim(), customer_email: form.customer_email.trim() });
    bookingDialog.value = false;
    success.value = 'Готово! Переговорная забронирована.';
    await refresh();
  } catch (error) {
    fieldErrors.value = error.errors || {};
    actionError.value = error.status === 422 ? 'Проверьте поля формы.' : error.message;
    if (error.status === 409) await refresh();
  } finally { pending.value = false; }
}
async function submitCancel() {
  if (isBusy.value) return;
  pending.value = true;
  actionError.value = '';
  try {
    await cancel(activeSlot.value.booking_id);
    cancelDialog.value = false;
    success.value = 'Бронирование отменено.';
    await refresh();
  } catch (error) {
    actionError.value = error.message;
  } finally { pending.value = false; }
}
onMounted(() => {
  refresh();
  timer = setInterval(() => { clock.value = Date.now(); }, 1000);
});
onUnmounted(() => clearInterval(timer));
</script>

<template>
  <div class="shell">
    <header class="topbar">
      <a class="brand" href="/" aria-label="Mini Booking — главная"><span class="brand-icon"><i class="pi pi-th-large" aria-hidden="true"></i></span>mini<span class="brand-light">booking</span><span class="brand-dot">.</span></a>
      <span class="office-label"><span class="live-dot"></span>Офисное пространство</span>
    </header>
    <main>
      <div class="intro"><span class="eyebrow">ОДНА КОМНАТА. МНОГО ХОРОШИХ ИДЕЙ.</span><h1>Место для вашей<br><span>следующей встречи.</span></h1><p>Выберите удобное время — мы сохраним его за вами.</p></div>
      <div class="workspace">
        <aside class="room-card">
          <div class="room-illustration" aria-hidden="true"><div class="room-grid"></div><div class="window"><span></span><span></span><span></span></div><div class="chair c1"></div><div class="chair c2"></div><div class="chair c3"></div><div class="chair c4"></div><div class="table"><div class="notebook"></div><div class="cup"></div></div><div class="plant"><i class="pi pi-sparkles"></i></div><span class="room-number">01</span></div>
          <div class="room-details"><span class="eyebrow">ПЕРЕГОВОРНАЯ КОМНАТА</span><h2>Комната для идей</h2><p class="room-description">Для встреч, обсуждений и новых планов.</p><div class="room-feature"><i class="pi pi-clock" aria-hidden="true"></i><div>09:00 — 18:00<small>Каждый день, включая выходные</small></div></div><div class="room-feature"><i class="pi pi-calendar" aria-hidden="true"></i><div>Встречи по 1 часу<small>Расписание на 7 дней</small></div></div><div class="timezone"><i class="pi pi-globe" aria-hidden="true"></i>Часовой пояс: {{ loaded ? timezone : 'загрузка…' }}</div></div>
        </aside>
        <section class="schedule" aria-label="Расписание переговорной">
          <div class="schedule-heading"><div><span class="eyebrow">ПЛАНИРУЙТЕ ПРОСТО</span><h2>Выберите время</h2></div><Button icon="pi pi-refresh" label="Обновить" text severity="secondary" :loading="loading" :disabled="isBusy" @click="refresh" /></div>
          <Message v-if="loadError" severity="error" :closable="false"><div class="error-row"><span>{{ loadError }} Данные могут быть неактуальны.</span><Button label="Повторить" size="small" severity="danger" :disabled="isBusy" @click="refresh" /></div></Message>
          <Message v-if="success" severity="success" :closable="true" @close="success = ''" role="status">{{ success }}</Message>
          <template v-if="!loaded && loading"><Skeleton height="76px" class="skeleton" /><Skeleton v-for="n in 4" :key="n" height="70px" class="skeleton" /></template>
          <template v-else-if="loaded">
            <div class="date-tabs" role="group" aria-label="Дата встречи"><button v-for="day in dates" :key="day.value" class="date-tab" :class="{ selected: selectedDate === day.value }" :aria-pressed="selectedDate === day.value" :disabled="pending" @click="selectedDate = day.value"><span>{{ dateLabel(day.startsAt, { weekday: 'short', day: undefined, month: undefined }) }}</span><strong>{{ new Intl.DateTimeFormat('ru-RU', { day: 'numeric', timeZone: timezone }).format(new Date(day.startsAt)) }}</strong><span>{{ new Intl.DateTimeFormat('ru-RU', { month: 'short', timeZone: timezone }).format(new Date(day.startsAt)).replace('.', '') }}</span></button></div>
            <Select v-model="selectedDate" :options="dates" option-label="label" option-value="value" class="mobile-date" aria-label="Дата встречи" :disabled="pending" />
            <div class="day-heading"><h3>{{ selectedDay ? dateLabel(selectedDay.startsAt, { weekday: 'long' }) : 'Нет расписания' }}</h3><span><span class="live-dot"></span>{{ availableCount }} свободно</span></div>
            <div class="slot-list" :aria-busy="isBusy">
              <article v-for="slot in visibleSlots" :key="slot.id" class="slot" :class="status(slot)"><div class="slot-time"><i class="pi pi-clock" aria-hidden="true"></i><span>{{ time(slot.starts_at) }}<span class="time-dash"> — </span>{{ time(slot.ends_at) }}</span></div><Tag :value="labels[status(slot)]" :severity="severities[status(slot)]" rounded /><Button v-if="status(slot) === 'available'" label="Забронировать" icon="pi pi-arrow-up-right" icon-pos="right" size="small" :disabled="isBusy || !!loadError" @click="openBooking(slot)" /><Button v-else-if="slot.booking_id" label="Отменить" severity="secondary" text size="small" :disabled="isBusy || !!loadError" @click="openCancel(slot)" /><span v-else class="past-action">Недоступно</span></article>
            </div>
            <p v-if="!visibleSlots.length" class="empty-state">Слотов пока нет. Заполните расписание командой Seeder из README.</p>
            <p class="schedule-note"><i class="pi pi-info-circle" aria-hidden="true"></i>Каждый слот — один час. Бронирование можно отменить в любой момент.</p>
          </template>
        </section>
      </div>
    </main>
    <footer><span>mini booking <span class="footer-dot">/</span> меньше переписок, больше встреч</span><span>Ваше время — в порядке.</span></footer>
  </div>

  <Dialog v-model:visible="bookingDialog" modal header="Забронировать переговорную" :style="{ width: '460px', maxWidth: 'calc(100vw - 32px)' }" :closable="!pending" :close-on-escape="!pending" :dismissable-mask="false">
    <p v-if="activeSlot" class="dialog-slot">{{ dateLabel(activeSlot.starts_at) }} · {{ time(activeSlot.starts_at) }}–{{ time(activeSlot.ends_at) }}<small>{{ timezone }}</small></p>
    <form @submit.prevent="submitBooking">
      <fieldset :disabled="pending" class="booking-form"><label for="customer-name">Ваше имя</label><InputText id="customer-name" v-model="form.customer_name" autofocus autocomplete="name" required maxlength="120" :invalid="!!fieldErrors.customer_name" aria-describedby="name-error" placeholder="Как к вам обращаться?" /><small v-if="fieldErrors.customer_name" id="name-error" class="field-error">{{ fieldErrors.customer_name[0] }}</small><label for="customer-email">Email</label><InputText id="customer-email" v-model="form.customer_email" type="email" autocomplete="email" required maxlength="254" :invalid="!!fieldErrors.customer_email" aria-describedby="email-error" placeholder="you@company.ru" /><small v-if="fieldErrors.customer_email" id="email-error" class="field-error">{{ fieldErrors.customer_email[0] }}</small><small v-if="fieldErrors.slot_id" class="field-error">{{ fieldErrors.slot_id[0] }}</small></fieldset>
      <Message v-if="actionError" severity="error" :closable="false" class="dialog-error">{{ actionError }}</Message>
      <p class="demo-note">Демо без авторизации: отмена доступна всем, кто видит бронирование.</p>
      <div class="dialog-actions"><Button label="Назад" text severity="secondary" :disabled="pending" @click="bookingDialog = false" /><Button type="submit" :label="actionError ? 'Повторить бронирование' : 'Забронировать'" :loading="pending" :disabled="isBusy" /></div>
    </form>
  </Dialog>
  <Dialog v-model:visible="cancelDialog" modal header="Отменить бронирование?" :style="{ width: '430px', maxWidth: 'calc(100vw - 32px)' }" :closable="!pending" :close-on-escape="!pending">
    <p v-if="activeSlot" class="dialog-slot">{{ dateLabel(activeSlot.starts_at) }} · {{ time(activeSlot.starts_at) }}–{{ time(activeSlot.ends_at) }}<small>{{ timezone }}</small></p><p>После отмены будущее время снова станет доступно для встречи.</p><Message v-if="actionError" severity="error" :closable="false">{{ actionError }}</Message><div class="dialog-actions"><Button label="Оставить" text severity="secondary" :disabled="pending" @click="cancelDialog = false" /><Button :label="actionError ? 'Повторить отмену' : 'Да, отменить'" severity="danger" :loading="pending" :disabled="isBusy" @click="submitCancel" /></div>
  </Dialog>
</template>
