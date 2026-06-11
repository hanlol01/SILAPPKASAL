export type TokenPersistence = "local" | "session";

const TOKEN_KEY = "silappkasal_auth_token";
const TOKEN_PERSISTENCE_KEY = "silappkasal_auth_token_persistence";

function canUseStorage() {
  return typeof window !== "undefined";
}

export function getLocalStorageItem(key: string) {
  if (!canUseStorage()) return null;
  return window.localStorage.getItem(key);
}

export function setLocalStorageItem(key: string, value: string) {
  if (!canUseStorage()) return;
  window.localStorage.setItem(key, value);
}

export function removeLocalStorageItem(key: string) {
  if (!canUseStorage()) return;
  window.localStorage.removeItem(key);
}

export function getSessionStorageItem(key: string) {
  if (!canUseStorage()) return null;
  return window.sessionStorage.getItem(key);
}

export function setSessionStorageItem(key: string, value: string) {
  if (!canUseStorage()) return;
  window.sessionStorage.setItem(key, value);
}

export function removeSessionStorageItem(key: string) {
  if (!canUseStorage()) return;
  window.sessionStorage.removeItem(key);
}

export function getAuthToken() {
  return getLocalStorageItem(TOKEN_KEY) ?? getSessionStorageItem(TOKEN_KEY);
}

export function setAuthToken(token: string, persistence: TokenPersistence) {
  clearAuthToken();

  if (persistence === "local") {
    setLocalStorageItem(TOKEN_KEY, token);
    setLocalStorageItem(TOKEN_PERSISTENCE_KEY, persistence);
    return;
  }

  setSessionStorageItem(TOKEN_KEY, token);
  setSessionStorageItem(TOKEN_PERSISTENCE_KEY, persistence);
}

export function clearAuthToken() {
  removeLocalStorageItem(TOKEN_KEY);
  removeLocalStorageItem(TOKEN_PERSISTENCE_KEY);
  removeSessionStorageItem(TOKEN_KEY);
  removeSessionStorageItem(TOKEN_PERSISTENCE_KEY);
}

export function hasAuthToken() {
  return Boolean(getAuthToken());
}
