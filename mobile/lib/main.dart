import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'providers/auth_provider.dart';
import 'providers/event_provider.dart';
import 'providers/checkin_provider.dart';
import 'screens/login_screen.dart';
import 'screens/events_screen.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const HiEventsApp());
}

class HiEventsApp extends StatelessWidget {
  const HiEventsApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthProvider()),
        ChangeNotifierProxyProvider<AuthProvider, EventProvider>(
          create: (_) => EventProvider(),
          update: (_, auth, events) => events!..updateAuth(auth),
        ),
        ChangeNotifierProxyProvider<AuthProvider, CheckInProvider>(
          create: (_) => CheckInProvider(),
          update: (_, auth, checkin) => checkin!..updateAuth(auth),
        ),
      ],
      child: MaterialApp(
        title: 'Hi.Events Check-In',
        debugShowCheckedModeBanner: false,
        theme: ThemeData(
          colorScheme: ColorScheme.fromSeed(
            seedColor: const Color(0xFFCD58DD),
            brightness: Brightness.dark,
          ),
          useMaterial3: true,
          scaffoldBackgroundColor: const Color(0xFF18181B),
        ),
        home: Consumer<AuthProvider>(
          builder: (context, auth, _) {
            if (auth.isAuthenticated) {
              return const EventsScreen();
            }
            return const LoginScreen();
          },
        ),
      ),
    );
  }
}
