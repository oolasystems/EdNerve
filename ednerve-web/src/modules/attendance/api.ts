import apiClient from "../../shared/lib/api-client";

export type AttendanceStatus = "present" | "absent" | "late";

export interface AttendanceFaculty {
  id: number;
  name: string;
  email: string;
}

export interface AttendanceSubject {
  id: number;
  code: string;
  name: string;
  subject_type: string;
  credits: number;
}

export interface AttendanceProgramme {
  id: number;
  code: string;
  name: string;
}

export interface TimetableEntry {
  id: number;
  programme_id: number;
  semester: number;
  section: string;
  day_of_week: number; // 1 = Monday ... 6 = Saturday
  period_no: number;
  subject_id: number;
  faculty_user_id: number | null;
  room: string | null;
  status: "active" | "archived";
  subject?: AttendanceSubject | null;
  programme?: AttendanceProgramme | null;
  faculty?: AttendanceFaculty | null;
}

export interface TimetableQuery {
  programme_id?: number;
  semester?: number;
  section?: string;
  mine?: "1";
}

export interface AttendanceSheet {
  id: number;
  institution_id: number;
  timetable_entry_id: number;
  date: string;
  marked_by: number | null;
}

export interface AttendanceRosterRow {
  student_profile_id: number;
  application_number: string | null;
  name: string;
  email: string;
  status: AttendanceStatus | null;
}

export interface OpenSheetResponse {
  sheet: AttendanceSheet;
  entry: TimetableEntry;
  roster: AttendanceRosterRow[];
}

export interface AttendanceSummarySubject {
  subject_id: number;
  attended: number;
  total: number;
  percent: number;
}

export interface AttendanceSummaryRow {
  student: {
    id: number;
    application_number: string | null;
    name: string;
    email: string;
  };
  subjects: AttendanceSummarySubject[];
  total: {
    attended: number;
    total: number;
    percent: number;
  };
}

export interface AttendanceSummaryResponse {
  programme: AttendanceProgramme;
  section: string;
  semester: number | null;
  subjects: AttendanceSubject[];
  rows: AttendanceSummaryRow[];
}

export const attendanceService = {
  async getTimetable(params?: TimetableQuery): Promise<{ timetable: TimetableEntry[] }> {
    const response = await apiClient.get<{ timetable: TimetableEntry[] }>("/timetable", { params });
    return response.data;
  },

  async getMyTimetable(): Promise<{ timetable: TimetableEntry[] }> {
    const response = await apiClient.get<{ timetable: TimetableEntry[] }>("/timetable/mine");
    return response.data;
  },

  async createEntry(payload: {
    programme_id: number;
    semester: number;
    section?: string;
    day_of_week: number;
    period_no: number;
    subject_id: number;
    faculty_user_id?: number | null;
    room?: string;
  }) {
    const response = await apiClient.post("/timetable/entries", payload);
    return response.data;
  },

  async updateEntry(
    id: number,
    payload: Partial<{
      programme_id: number;
      semester: number;
      section: string;
      day_of_week: number;
      period_no: number;
      subject_id: number;
      faculty_user_id: number | null;
      room: string | null;
      status: "active" | "archived";
    }>
  ) {
    const response = await apiClient.patch(`/timetable/entries/${id}`, payload);
    return response.data;
  },

  async deleteEntry(id: number) {
    const response = await apiClient.delete(`/timetable/entries/${id}`);
    return response.data;
  },

  async openSheet(timetableEntryId: number, date: string): Promise<OpenSheetResponse> {
    const response = await apiClient.post<OpenSheetResponse>("/attendance/sheets/open", {
      timetable_entry_id: timetableEntryId,
      date,
    });
    return response.data;
  },

  async getSheet(sheetId: number): Promise<OpenSheetResponse> {
    const response = await apiClient.get<OpenSheetResponse>(`/attendance/sheets/${sheetId}`);
    return response.data;
  },

  async saveSheet(
    sheetId: number,
    records: Array<{ student_profile_id: number; status: AttendanceStatus }>
  ): Promise<{ message: string; saved: number }> {
    const response = await apiClient.put(`/attendance/sheets/${sheetId}/records`, { records });
    return response.data;
  },

  async getSummary(params: {
    programme_id: number;
    semester?: number;
    section?: string;
  }): Promise<AttendanceSummaryResponse> {
    const response = await apiClient.get<AttendanceSummaryResponse>("/attendance/summary", {
      params,
    });
    return response.data;
  },
};
