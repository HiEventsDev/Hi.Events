import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:provider/provider.dart';
import '../models/models.dart';
import '../providers/checkin_provider.dart';

class ScannerScreen extends StatefulWidget {
  final Event event;

  const ScannerScreen({super.key, required this.event});

  @override
  State<ScannerScreen> createState() => _ScannerScreenState();
}

class _ScannerScreenState extends State<ScannerScreen>
    with SingleTickerProviderStateMixin {
  final MobileScannerController _scannerController = MobileScannerController(
    detectionSpeed: DetectionSpeed.normal,
    facing: CameraFacing.back,
  );
  bool _isProcessing = false;
  CheckInResult? _lastResult;

  @override
  void initState() {
    super.initState();
    context.read<CheckInProvider>().loadStats(widget.event.id);
  }

  @override
  void dispose() {
    _scannerController.dispose();
    super.dispose();
  }

  Future<void> _onBarcodeDetected(BarcodeCapture capture) async {
    if (_isProcessing) return;
    final barcode = capture.barcodes.firstOrNull;
    if (barcode?.rawValue == null) return;

    setState(() => _isProcessing = true);

    // Parse the QR data - expected format: attendeeId:checkInListId or just attendeeId
    final parts = barcode!.rawValue!.split(':');
    final attendeeId = int.tryParse(parts[0]);
    final checkInListId = parts.length > 1 ? int.tryParse(parts[1]) : 1;

    if (attendeeId == null) {
      setState(() {
        _lastResult = CheckInResult(
          success: false,
          message: 'Invalid QR code format',
        );
        _isProcessing = false;
      });
      return;
    }

    final result = await context.read<CheckInProvider>().checkInAttendee(
          widget.event.id,
          attendeeId,
          checkInListId ?? 1,
        );

    setState(() {
      _lastResult = result;
      _isProcessing = false;
    });

    // Refresh stats after check-in
    if (result.success) {
      context.read<CheckInProvider>().loadStats(widget.event.id);
    }

    // Auto-reset after 3 seconds
    Future.delayed(const Duration(seconds: 3), () {
      if (mounted) {
        setState(() => _lastResult = null);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final stats = context.watch<CheckInProvider>().stats;

    return Scaffold(
      appBar: AppBar(
        title: Text(widget.event.title),
        actions: [
          if (stats != null)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Center(
                child: Text(
                  '${stats.checkedIn}/${stats.totalAttendees}',
                  style: const TextStyle(
                      fontSize: 16, fontWeight: FontWeight.bold),
                ),
              ),
            ),
        ],
      ),
      body: Stack(
        children: [
          MobileScanner(
            controller: _scannerController,
            onDetect: _onBarcodeDetected,
          ),
          // Scan overlay
          Center(
            child: Container(
              width: 280,
              height: 280,
              decoration: BoxDecoration(
                border: Border.all(
                  color: const Color(0xFFCD58DD),
                  width: 3,
                ),
                borderRadius: BorderRadius.circular(16),
              ),
            ),
          ),
          // Result banner
          if (_lastResult != null)
            Positioned(
              bottom: 0,
              left: 0,
              right: 0,
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 300),
                padding: const EdgeInsets.all(24),
                color: _lastResult!.success
                    ? Colors.green.shade800
                    : Colors.red.shade800,
                child: SafeArea(
                  top: false,
                  child: Row(
                    children: [
                      Icon(
                        _lastResult!.success
                            ? Icons.check_circle
                            : Icons.error,
                        color: Colors.white,
                        size: 32,
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Text(
                          _lastResult!.message,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          // Processing indicator
          if (_isProcessing)
            const Center(
              child: CircularProgressIndicator(
                color: Color(0xFFCD58DD),
              ),
            ),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () => _scannerController.toggleTorch(),
        child: const Icon(Icons.flashlight_on),
      ),
    );
  }
}
