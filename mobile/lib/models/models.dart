class Event {
  final int id;
  final String title;
  final String? description;
  final String? startDate;
  final String? endDate;
  final String status;
  final String? timezone;
  final String? currency;

  Event({
    required this.id,
    required this.title,
    this.description,
    this.startDate,
    this.endDate,
    required this.status,
    this.timezone,
    this.currency,
  });

  factory Event.fromJson(Map<String, dynamic> json) => Event(
        id: json['id'],
        title: json['title'],
        description: json['description'],
        startDate: json['start_date'],
        endDate: json['end_date'],
        status: json['status'],
        timezone: json['timezone'],
        currency: json['currency'],
      );
}

class Attendee {
  final int id;
  final String firstName;
  final String lastName;
  final String? email;
  final String? shortId;
  final String? publicId;
  final String status;
  final String? productTitle;
  final bool isCheckedIn;
  final String? lastCheckInAt;

  Attendee({
    required this.id,
    required this.firstName,
    required this.lastName,
    this.email,
    this.shortId,
    this.publicId,
    required this.status,
    this.productTitle,
    this.isCheckedIn = false,
    this.lastCheckInAt,
  });

  String get fullName => '$firstName $lastName';

  factory Attendee.fromJson(Map<String, dynamic> json) => Attendee(
        id: json['id'],
        firstName: json['first_name'],
        lastName: json['last_name'],
        email: json['email'],
        shortId: json['short_id'],
        publicId: json['public_id'],
        status: json['status'],
        productTitle: json['product_title'],
        isCheckedIn: json['is_checked_in'] ?? false,
        lastCheckInAt: json['last_check_in_at'],
      );
}

class CheckInResult {
  final bool success;
  final String message;
  final Attendee? attendee;

  CheckInResult({
    required this.success,
    required this.message,
    this.attendee,
  });
}

class CheckInStats {
  final int totalAttendees;
  final int checkedIn;
  final int notCheckedIn;

  CheckInStats({
    required this.totalAttendees,
    required this.checkedIn,
    required this.notCheckedIn,
  });

  double get checkInRate =>
      totalAttendees > 0 ? (checkedIn / totalAttendees) * 100 : 0;

  factory CheckInStats.fromJson(Map<String, dynamic> json) => CheckInStats(
        totalAttendees: json['total_attendees'] ?? 0,
        checkedIn: json['checked_in'] ?? 0,
        notCheckedIn: json['not_checked_in'] ?? 0,
      );
}
