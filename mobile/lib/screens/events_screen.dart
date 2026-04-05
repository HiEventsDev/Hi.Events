import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../providers/event_provider.dart';
import 'scanner_screen.dart';

class EventsScreen extends StatefulWidget {
  const EventsScreen({super.key});

  @override
  State<EventsScreen> createState() => _EventsScreenState();
}

class _EventsScreenState extends State<EventsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<EventProvider>().loadEvents();
    });
  }

  @override
  Widget build(BuildContext context) {
    final eventProvider = context.watch<EventProvider>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Events'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => eventProvider.loadEvents(),
          ),
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () => context.read<AuthProvider>().logout(),
          ),
        ],
      ),
      body: eventProvider.isLoading
          ? const Center(child: CircularProgressIndicator())
          : eventProvider.events.isEmpty
              ? const Center(child: Text('No events found'))
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: eventProvider.events.length,
                  itemBuilder: (context, index) {
                    final event = eventProvider.events[index];
                    return Card(
                      margin: const EdgeInsets.only(bottom: 12),
                      child: ListTile(
                        title: Text(event.title,
                            style:
                                const TextStyle(fontWeight: FontWeight.bold)),
                        subtitle: Text(event.startDate ?? 'No date set'),
                        trailing: Chip(
                          label: Text(event.status,
                              style: const TextStyle(fontSize: 12)),
                          backgroundColor:
                              event.status == 'LIVE' ? Colors.green : null,
                        ),
                        onTap: () {
                          eventProvider.selectEvent(event);
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => ScannerScreen(event: event),
                            ),
                          );
                        },
                      ),
                    );
                  },
                ),
    );
  }
}
