import 'package:flutter/material.dart';
import '../services/api_service.dart';

class AuthProvider extends ChangeNotifier {
  final ApiService _api = ApiService();
  bool _isAuthenticated = false;
  bool _isLoading = false;
  String? _error;
  Map<String, dynamic>? _user;

  bool get isAuthenticated => _isAuthenticated;
  bool get isLoading => _isLoading;
  String? get error => _error;
  Map<String, dynamic>? get user => _user;
  ApiService get api => _api;

  AuthProvider() {
    _initAuth();
  }

  Future<void> _initAuth() async {
    await _api.loadStoredAuth();
    _isAuthenticated = _api.isAuthenticated;
    notifyListeners();
  }

  Future<bool> login(String baseUrl, String email, String password) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      await _api.configure(baseUrl);
      final data = await _api.login(email, password);
      _user = data['user'];
      _isAuthenticated = true;
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _error = 'Login failed. Please check your credentials.';
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<void> logout() async {
    await _api.logout();
    _isAuthenticated = false;
    _user = null;
    notifyListeners();
  }
}
