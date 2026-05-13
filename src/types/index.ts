export type CaseStatus =
  | "received"
  | "verification"
  | "investigation"
  | "mediation"
  | "resolved"
  | "closed";

export type CaseCategory =
  | "verbal"
  | "physical"
  | "digital"
  | "stalking"
  | "discrimination"
  | "other";

export interface CaseReport {
  id: string;
  reporterName: string;
  anonymous: boolean;
  faculty: string;
  category: CaseCategory;
  date: string;
  assignedOfficer: string;
  status: CaseStatus;
  description: string;
  location: string;
  evidence: { type: "image" | "document"; name: string }[];
  timeline: { date: string; actor: string; action: string; note?: string }[];
  notes: { author: string; date: string; content: string }[];
}

export interface Article {
  id: string;
  title: string;
  category: string;
  thumbnail: string;
  content: string;
  author: string;
  publishDate: string;
  published: boolean;
}

export interface AppUser {
  id: string;
  name: string;
  email: string;
  role: "Super Admin" | "Satgas Officer" | "Reviewer" | "Counselor";
  active: boolean;
  lastActive: string;
}

export interface Notification {
  id: string;
  type: "report" | "status" | "reminder" | "article";
  title: string;
  message: string;
  date: string;
  read: boolean;
}
