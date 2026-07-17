import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import LanguageDetector from 'i18next-browser-languagedetector';

import commonId from './locales/id/common.json';
import authId from './locales/id/auth.json';
import portalId from './locales/id/portal.json';
import dashboardId from './locales/id/dashboard.json';

import commonEn from './locales/en/common.json';
import authEn from './locales/en/auth.json';
import portalEn from './locales/en/portal.json';
import dashboardEn from './locales/en/dashboard.json';

const resources = {
  id: {
    common: commonId,
    auth: authId,
    portal: portalId,
    dashboard: dashboardId,
  },
  en: {
    common: commonEn,
    auth: authEn,
    portal: portalEn,
    dashboard: dashboardEn,
  },
};

i18n
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    resources,
    supportedLngs: ['id', 'en'],
    load: 'languageOnly',
    nonExplicitSupportedLngs: true,
    fallbackLng: 'id',
    ns: ['common', 'auth', 'portal', 'dashboard'],
    defaultNS: 'common',
    interpolation: {
      escapeValue: false,
    },
    detection: {
      order: ['localStorage'],
      caches: ['localStorage'],
      lookupLocalStorage: 'i18nextLng',
    },
  });

export default i18n;
