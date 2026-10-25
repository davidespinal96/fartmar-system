import axios from "axios";

interface ApiErrorBody {
  message?: string;
  errors?: Record<string, string | string[]>;
}

export function getApiErrorMessage(error: unknown, fallback: string): string {
  if (!axios.isAxiosError<ApiErrorBody>(error)) return fallback;
  const validation = Object.values(error.response?.data?.errors ?? {})
    .flatMap(value => Array.isArray(value) ? value : [value]);
  return validation.join(" ") || error.response?.data?.message || fallback;
}

export function getApiErrorStatus(error: unknown): number | undefined {
  return axios.isAxiosError(error) ? error.response?.status : undefined;
}
