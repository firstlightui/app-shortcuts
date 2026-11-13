import Foundation

/// The queue of shortcut actions waiting for PHP.
///
/// An action's App Intent runs without opening the app, often before PHP has booted, and Siri waits
/// only a few seconds for an answer. So the intent writes the shortcut's id to this file and answers
/// at once; PHP takes the queue through AppShortcuts.TakeQueued when the first screen of a launch
/// mounts, or straight away when a native screen is live to hear the ActionsQueued event.
///
/// Intents run on the main actor and bridge functions on the PHP thread, so every read and write
/// holds the lock. The file is readable after the first unlock since boot, like the app's database.
enum AppShortcutsActions {
    /// Matches FirstlightUI\AppShortcuts\Events\ActionsQueued in PHP.
    static let queuedEvent = "FirstlightUI\\AppShortcuts\\Events\\ActionsQueued"

    /// The oldest entries are dropped past this, so a queue nobody drains cannot grow without end.
    private static let limit = 100

    private static let lock = NSLock()

    private static var file: URL {
        FileManager.default.urls(for: .applicationSupportDirectory, in: .userDomainMask)[0]
            .appendingPathComponent("firstlight-app-shortcuts", isDirectory: true)
            .appendingPathComponent("queued-actions.json")
    }

    /// Queues one run of an action and wakes PHP when a native screen is live.
    static func enqueue(shortcut: String) throws {
        try lock.withLock {
            var entries = read()
            entries.append([
                "id": UUID().uuidString.lowercased(),
                "shortcut": shortcut,
                "at": Int(Date().timeIntervalSince1970 * 1000),
            ])

            try write(Array(entries.suffix(limit)))
        }

        _ = NativeElementBridge.sendNativeEvent(eventName: queuedEvent, payloadJson: "{}")
    }

    /// Every queued entry, oldest first, leaving the queue empty.
    static func takeAll() -> [[String: Any]] {
        lock.withLock {
            let entries = read()

            if !entries.isEmpty {
                try? FileManager.default.removeItem(at: file)
            }

            return entries
        }
    }

    private static func read() -> [[String: Any]] {
        guard let data = try? Data(contentsOf: file),
              let entries = try? JSONSerialization.jsonObject(with: data) as? [[String: Any]] else {
            return []
        }

        return entries
    }

    private static func write(_ entries: [[String: Any]]) throws {
        try FileManager.default.createDirectory(at: file.deletingLastPathComponent(), withIntermediateDirectories: true)
        let data = try JSONSerialization.data(withJSONObject: entries)
        try data.write(to: file, options: [.atomic, .completeFileProtectionUntilFirstUserAuthentication])
    }
}
