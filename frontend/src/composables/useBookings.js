import { ref } from "vue";

export class ApiError extends Error {
  constructor(message, status = 0, errors = {}) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
}

async function request(path, options = {}) {
  let response;
  try {
    response = await fetch(`/api${path}`, {
      ...options,
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      signal: AbortSignal.timeout(15000),
    });
  } catch {
    throw new ApiError(
      "Не удалось связаться с сервером. Проверьте соединение и повторите действие.",
    );
  }
  const body =
    response.status === 204 ? null : await response.json().catch(() => null);
  if (!response.ok) {
    const message =
      response.status >= 500
        ? "Сервер временно недоступен. Попробуйте ещё раз."
        : body?.message || "Не удалось выполнить действие. Повторите попытку.";
    throw new ApiError(message, response.status, body?.errors);
  }
  if (response.status !== 204 && body === null)
    throw new ApiError("Сервер вернул некорректный ответ. Повторите попытку.");
  return body;
}

export function useBookings() {
  const slots = ref([]);
  const timezone = ref("UTC");
  const loading = ref(false);
  const pending = ref(false);
  const loadError = ref("");
  const loaded = ref(false);
  const serverOffset = ref(0);

  async function load() {
    if (loading.value) return false;
    loading.value = true;
    loadError.value = "";
    try {
      const result = await request("/slots");
      slots.value = result.data;
      timezone.value = result.timezone;
      serverOffset.value = Date.parse(result.server_time) - Date.now();
      loaded.value = true;
      return true;
    } catch (error) {
      loadError.value = error.message;
      return false;
    } finally {
      loading.value = false;
    }
  }

  async function create(data) {
    return request("/bookings", { method: "POST", body: JSON.stringify(data) });
  }
  async function cancel(id) {
    return request(`/bookings/${id}`, { method: "DELETE" });
  }
  return {
    slots,
    timezone,
    loading,
    pending,
    loaded,
    loadError,
    serverOffset,
    load,
    create,
    cancel,
  };
}
