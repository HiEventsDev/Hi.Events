import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class ApiService {
  static const String _baseUrlKey = 'api_base_url';
  static const String _tokenKey = 'auth_token';

  final Dio _dio;
  final FlutterSecureStorage _storage;
  String? _token;

  ApiService()
      : _dio = Dio(),
        _storage = const FlutterSecureStorage() {
    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) {
        if (_token != null) {
          options.headers['Authorization'] = 'Bearer $_token';
        }
        options.headers['Accept'] = 'application/json';
        return handler.next(options);
      },
      onError: (error, handler) {
        if (error.response?.statusCode == 401) {
          _token = null;
          _storage.delete(key: _tokenKey);
        }
        return handler.next(error);
      },
    ));
  }

  Future<void> configure(String baseUrl) async {
    _dio.options.baseUrl = baseUrl.endsWith('/') ? baseUrl : '$baseUrl/';
    await _storage.write(key: _baseUrlKey, value: baseUrl);
  }

  Future<void> loadStoredAuth() async {
    _token = await _storage.read(key: _tokenKey);
    final baseUrl = await _storage.read(key: _baseUrlKey);
    if (baseUrl != null) {
      _dio.options.baseUrl = baseUrl.endsWith('/') ? baseUrl : '$baseUrl/';
    }
  }

  bool get isAuthenticated => _token != null;

  Future<Map<String, dynamic>> login(String email, String password) async {
    final response = await _dio.post('auth/login', data: {
      'email': email,
      'password': password,
    });
    _token = response.data['token'];
    await _storage.write(key: _tokenKey, value: _token!);
    return response.data;
  }

  Future<void> logout() async {
    _token = null;
    await _storage.delete(key: _tokenKey);
  }

  Future<List<dynamic>> getEvents({int page = 1}) async {
    final response = await _dio.get('events', queryParameters: {'page': page});
    return response.data['data'];
  }

  Future<List<dynamic>> getAttendees(int eventId,
      {int page = 1, String? search}) async {
    final params = <String, dynamic>{'page': page};
    if (search != null && search.isNotEmpty) {
      params['filter_fields[]'] = 'public_id';
      params['filter_values[]'] = search;
    }
    final response =
        await _dio.get('events/$eventId/attendees', queryParameters: params);
    return response.data['data'];
  }

  Future<Map<String, dynamic>> checkIn(
      int eventId, int attendeeId, int checkInListId) async {
    final response = await _dio.post(
      'events/$eventId/check-in-lists/$checkInListId/attendees/$attendeeId/check-in',
    );
    return response.data;
  }

  Future<Map<String, dynamic>> getCheckInStats(int eventId) async {
    final response = await _dio.get('events/$eventId/check_in_stats');
    return response.data['data'];
  }

  Future<List<dynamic>> getCheckInLists(int eventId) async {
    final response = await _dio.get('events/$eventId/check-in-lists');
    return response.data['data'];
  }
}
