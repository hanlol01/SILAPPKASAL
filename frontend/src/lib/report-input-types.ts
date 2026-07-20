export interface ReportInputReference {
  code: string;
  name: string;
}

export type ReporterAccountProjection =
  | {
      source: "current_account";
      masked: true;
    }
  | {
      source: "current_account";
      masked: false;
      name: string;
      nim: string | null;
      email: string;
      phone_number: string | null;
      faculty: ReportInputReference | null;
      study_program: ReportInputReference | null;
    };

export interface ReportInputDetails {
  identification: {
    report_type: string;
    category: ReportInputReference | null;
  };
  incident: {
    chronology: string;
    incident_date: string | null;
    incident_time: string | null;
    incident_location: string;
    location_type: ReportInputReference | null;
  };
  respondent: {
    name: string | null;
    campus_status: ReportInputReference | null;
    relation: ReportInputReference | null;
    details: string | null;
    witness_information: string | null;
    confidential_reporter_contact: string | null;
  };
  reporter_account: ReporterAccountProjection;
}
