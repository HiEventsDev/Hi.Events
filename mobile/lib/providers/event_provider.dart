import 'package:flutter/material.dart';
import '../models/models.dart';
import '../providers/auth_provider.dart';

class EventProvider extends ChangeNotifier {
  AuthProvider? _auth;
  List<Event> _events = [];
  Event? _selectedEvent;
  bool _isLoading = false;

  List<Event> get events => _events;
  Event? get selectedEvent => _selectedEvent;
  bool get isLoading => _isLoading;

  void updateAuth(AuthProvider auth) {
    _auth = auth;
  }

  Future<void> loadEvents() async {
    if (_auth?.api == null) return;
    _isLoading = true;
    notifyListeners();

    try {
      final data = await _auth!.api.getEvents();
      _events = data.map((e) => Event.fromJson(e)).toList();
    } catch (e) {
      // Handle error
    }

    _isLoading = false;
    notifyListeners();
  }

  void selectEvent(Event event) {
    _selectedEvent = event;
    notifyListeners();
  }
}
