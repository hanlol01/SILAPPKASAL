export type TokenPersistence = "local" | "session";

const TOKEN_KEY = "silappkasal_auth_token";
const TOKEN_PERSISTENCE_KEY = "silappkasal_auth_token_persistence";

function canUseStorage() {
  return typeof window !== "undefined";
}

export function getLocalStorageItem(key: string) {
  if (!canUseStorage()) return null;
  try {
    return window.localStorage.getItem(key);
  } catch {
    return null;
  }
}

export function setLocalStorageItem(key: string, value: string) {
  if (!canUseStorage()) return false;
  try {
    window.localStorage.setItem(key, value);
    return window.localStorage.getItem(key) === value;
  } catch {
    return false;
  }
}

export function removeLocalStorageItem(key: string) {
  if (!canUseStorage()) return;
  try {
    window.localStorage.removeItem(key);
  } catch {
    // Ignore unavailable storage during SSR or restricted browser contexts.
  }
}

export function getSessionStorageItem(key: string) {
  if (!canUseStorage()) return null;
  try {
    return window.sessionStorage.getItem(key);
  } catch {
    return null;
  }
}

export function setSessionStorageItem(key: string, value: string) {
  if (!canUseStorage()) return false;
  try {
    window.sessionStorage.setItem(key, value);
    return window.sessionStorage.getItem(key) === value;
  } catch {
    return false;
  }
}

export function removeSessionStorageItem(key: string) {
  if (!canUseStorage()) return;
  try {
    window.sessionStorage.removeItem(key);
  } catch {
    // Ignore unavailable storage during SSR or restricted browser contexts.
  }
}

export function getAuthToken() {
  return getLocalStorageItem(TOKEN_KEY) ?? getSessionStorageItem(TOKEN_KEY);
}

export function setAuthToken(token: string, persistence: TokenPersistence) {
  if (!token || typeof token !== "string") {
    throw new Error("Login response did not include an auth token");
  }

  clearAuthToken();

  if (persistence === "local") {
    const tokenSaved = setLocalStorageItem(TOKEN_KEY, token);
    const persistenceSaved = setLocalStorageItem(TOKEN_PERSISTENCE_KEY, persistence);
    if (!tokenSaved || !persistenceSaved || getLocalStorageItem(TOKEN_KEY) !== token) {
      clearAuthToken();
      throw new Error("Auth token could not be saved to local storage");
    }
    return;
  }

  const tokenSaved = setSessionStorageItem(TOKEN_KEY, token);
  const persistenceSaved = setSessionStorageItem(TOKEN_PERSISTENCE_KEY, persistence);
  if (!tokenSaved || !persistenceSaved || getSessionStorageItem(TOKEN_KEY) !== token) {
    clearAuthToken();
    throw new Error("Auth token could not be saved to session storage");
  }
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
