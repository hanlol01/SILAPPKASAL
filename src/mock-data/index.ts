import type { CaseReport, Article, AppUser, Notification } from "@/types";

const faculties = [
  "Engineering",
  "Medicine",
  "Law",
  "Economics",
  "Arts & Humanities",
  "Computer Science",
  "Psychology",
  "Education",
];

const officers = [
  "Dr. Sarah Putri",
  "Andi Wijaya, M.Psi",
  "Maya Lestari, S.H.",
  "Rizki Pratama",
  "Dewi Anggraini",
];

const categories: CaseReport["category"][] = [
  "verbal",
  "physical",
  "digital",
  "stalking",
  "discrimination",
  "other",
];

const statuses: CaseReport["status"][] = [
  "received",
  "verification",
  "investigation",
  "mediation",
  "resolved",
  "closed",
];

const reporterNames = [
  "Aisha N.",
  "Bima R.",
  "Cinta P.",
  "Dimas A.",
  "Elsa M.",
  "Farah S.",
  "Gilang H.",
  "Hana K.",
  "Indra W.",
  "Jihan T.",
];

function rand<T>(arr: T[], i: number): T {
  return arr[i % arr.length];
}

export const mockCases: CaseReport[] = Array.from({ length: 28 }).map((_, i) => {
  const anonymous = i % 4 === 0;
  const date = new Date(2025, (i % 12), ((i * 3) % 27) + 1).toISOString();
  return {
    id: `RPT-2025-${String(1000 + i).padStart(4, "0")}`,
    reporterName: anonymous ? "Anonymous" : rand(reporterNames, i),
    anonymous,
    faculty: rand(faculties, i),
    category: rand(categories, i),
    date,
    assignedOfficer: rand(officers, i),
    status: rand(statuses, i),
    description:
      "Reporter described an incident that took place on campus involving inappropriate conduct. The reporter requests confidential handling and emotional support during the investigation.",
    location: rand(
      ["Main Library", "Building A Lab", "Cafeteria", "Online (WhatsApp)", "Parking Lot", "Lecture Hall 3"],
      i,
    ),
    evidence: [
      { type: "image", name: "screenshot-1.png" },
      { type: "document", name: "statement.pdf" },
    ],
    timeline: [
      { date, actor: "System", action: "Report received" },
      {
        date: new Date(new Date(date).getTime() + 86400000).toISOString(),
        actor: "Maya Lestari",
        action: "Initial verification started",
      },
      {
        date: new Date(new Date(date).getTime() + 3 * 86400000).toISOString(),
        actor: rand(officers, i),
        action: "Investigator assigned",
      },
    ],
    notes: [
      {
        author: rand(officers, i),
        date,
        content: "Reporter contacted via secure channel. Awaiting additional witness statements.",
      },
    ],
  };
});

export const mockArticles: Article[] = [
  {
    id: "art-1",
    title: "Recognizing Subtle Forms of Harassment on Campus",
    category: "Awareness campaign",
    thumbnail: "",
    content:
      "Harassment is not always overt. Learn the subtle signals — comments, jokes, exclusion — that can create a hostile environment.",
    author: "Dr. Sarah Putri",
    publishDate: "2025-04-12",
    published: true,
  },
  {
    id: "art-2",
    title: "How to Support a Friend Who Has Been Harassed",
    category: "Counseling",
    thumbnail: "",
    content:
      "Listening, validating, and offering safe options are the most powerful tools when someone confides in you.",
    author: "Andi Wijaya",
    publishDate: "2025-03-22",
    published: true,
  },
  {
    id: "art-3",
    title: "Campus Policy Update: Reporting Channels 2025",
    category: "Campus policy",
    thumbnail: "",
    content: "An overview of the new confidential reporting channels and protective measures available this year.",
    author: "Satgas PPKS",
    publishDate: "2025-02-08",
    published: true,
  },
  {
    id: "art-4",
    title: "Bystander Intervention: The 5 D's",
    category: "Prevention",
    thumbnail: "",
    content: "Direct, Distract, Delegate, Delay, Document — practical ways to intervene safely.",
    author: "Dewi Anggraini",
    publishDate: "2025-05-01",
    published: false,
  },
];

export const mockUsers: AppUser[] = [
  { id: "u1", name: "Dr. Sarah Putri", email: "sarah@safecampus.id", role: "Super Admin", active: true, lastActive: "2025-05-12" },
  { id: "u2", name: "Andi Wijaya", email: "andi@safecampus.id", role: "Satgas Officer", active: true, lastActive: "2025-05-13" },
  { id: "u3", name: "Maya Lestari", email: "maya@safecampus.id", role: "Reviewer", active: true, lastActive: "2025-05-11" },
  { id: "u4", name: "Rizki Pratama", email: "rizki@safecampus.id", role: "Counselor", active: false, lastActive: "2025-04-28" },
  { id: "u5", name: "Dewi Anggraini", email: "dewi@safecampus.id", role: "Satgas Officer", active: true, lastActive: "2025-05-13" },
];

export const mockNotifications: Notification[] = [
  { id: "n1", type: "report", title: "New report received", message: "RPT-2025-1027 was submitted anonymously.", date: "2025-05-13T09:14:00Z", read: false },
  { id: "n2", type: "status", title: "Status updated", message: "RPT-2025-1018 moved to Investigation.", date: "2025-05-13T08:02:00Z", read: false },
  { id: "n3", type: "reminder", title: "Investigation reminder", message: "Case RPT-2025-1009 has been pending review for 5 days.", date: "2025-05-12T15:30:00Z", read: true },
  { id: "n4", type: "article", title: "Article published", message: '"Bystander Intervention: The 5 D\'s" is now live.', date: "2025-05-11T11:00:00Z", read: true },
];

export const monthlyTrend = [
  { month: "Jan", reports: 12, resolved: 8 },
  { month: "Feb", reports: 18, resolved: 11 },
  { month: "Mar", reports: 22, resolved: 15 },
  { month: "Apr", reports: 26, resolved: 18 },
  { month: "May", reports: 31, resolved: 22 },
  { month: "Jun", reports: 24, resolved: 20 },
  { month: "Jul", reports: 19, resolved: 17 },
  { month: "Aug", reports: 28, resolved: 21 },
  { month: "Sep", reports: 35, resolved: 26 },
  { month: "Oct", reports: 30, resolved: 24 },
  { month: "Nov", reports: 27, resolved: 23 },
  { month: "Dec", reports: 21, resolved: 19 },
];

export const categoryDistribution = [
  { name: "Verbal", value: 38 },
  { name: "Digital", value: 27 },
  { name: "Physical", value: 14 },
  { name: "Stalking", value: 11 },
  { name: "Discrimination", value: 18 },
  { name: "Other", value: 6 },
];

export const facultyDistribution = faculties.map((f, i) => ({
  faculty: f,
  cases: 8 + ((i * 7) % 22),
}));
