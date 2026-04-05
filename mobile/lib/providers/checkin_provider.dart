import 'package:flutter/material.dart';
import '../models/models.dart';
import '../providers/auth_provider.dart';

class CheckInProvider extends ChangeNotifier {
  AuthProvider? _auth;
  List<Attendee> _attendees = [];
  CheckInStats? _stats;
  bool _isLoading = false;
  CheckInResult? _lastResult;

  List<Attendee> get attendees => _attendees;
  CheckInStats? get stats => _stats;
  bool get isLoading => _isLoading;
  CheckInResult? get lastResult => _lastResult;

  void updateAuth(AuthProvider auth) {
    _auth = auth;
  }

  Future<void> loadAttendees(int eventId, {String? search}) async {
    if (_auth?.api == null) return;
    _isLoading = true;
    notifyListeners();

    try {
      final data =
          await _auth!.api.getAttendees(eventId, search: search);
      _attendees = data.map((a) => Attendee.fromJson(a)).toList();
    } catch (e) {
      // Handle error
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<CheckInResult> checkInAttendee(
      int eventId, int attendeeId, int checkInListId) async {
    try {
      await _auth!.api.checkIn(eventId, attendeeId, checkInListId);
      _lastResult = CheckInResult(
        success: true,
        message: 'Check-in successful!',
      );
    } catch (e) {
      _lastResult = CheckInResult(
        success: false,
        message: 'Check-in failed. Please try again.',
      );
    }
    notifyListeners();
    return _lastResult!;
  }

  Future<void> loadStats(int eventId) async {
    if (_auth?.api == null) return;
    try {
      final data = await _auth!.api.getCheckInStats(eventId);
      _stats = CheckInStats.fromJson(data);
      notifyListeners();
    } catch (e) {
      // Handle error
    }
  }
}
