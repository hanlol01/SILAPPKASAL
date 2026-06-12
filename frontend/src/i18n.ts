import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import LanguageDetector from 'i18next-browser-languagedetector';

import commonId from './locales/id/common.json';
import authId from './locales/id/auth.json';
import portalId from './locales/id/portal.json';

import commonEn from './locales/en/common.json';
import authEn from './locales/en/auth.json';
import portalEn from './locales/en/portal.json';

const resources = {
  id: {
    common: commonId,
    auth: authId,
    portal: portalId,
  },
  en: {
    common: commonEn,
    auth: authEn,
    portal: portalEn,
  },
};

i18n
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    resources,
    fallbackLng: 'id',
    ns: ['common', 'auth', 'portal'],
    defaultNS: 'common',
    interpolation: {
      escapeValue: false,
    },
    detection: {
      order: ['localStorage'],
      caches: ['localStorage'],
    },
  });

export default i18n;
