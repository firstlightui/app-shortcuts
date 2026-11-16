import Foundation
import UIKit

/// Runs shortcut actions in PHP as their App Intents fire, and queues those PHP can't run then.
///
/// An action's intent runs in the app's process with no screen: iOS launches the app in the
/// background when it is closed. NativePHP only boots PHP when a screen appears, so:
///
/// - When the app is closed, the intent boots the app's own PHP and runs the action there. Only
///   when the extracted Laravel bundle is current: unpacking it needs setup that NativePHP keeps
///   private, and a DEBUG build unpacks on every launch.
/// - When the app has a screen, or PHP is already up, the action runs in NativePHP's ephemeral
///   runtime, a PHP context beside the one native screens use, once the app's PHP has booted.
/// - Otherwise the action is queued, and PHP takes the queue through AppShortcuts.TakeQueued when
///   the first screen of a launch mounts, or straight away when a live screen hears ActionsQueued.
///
/// NativePHP boots PHP once per process, and a second boot started while the first runs fails, so
/// the app and an intent must never both boot it. That is why a closed app's boot and action run
/// on the main thread: NativePHP starts its own boot from the splash screen's onAppear, also on the
/// main thread, so an app opened meanwhile waits and then finds PHP up. With a screen, the app is
/// the one booting, and the intent waits for it.
///
/// Intents run on the main actor and bridge functions on the PHP thread, so every queue read and
/// write holds the lock. The queue file is readable after the first unlock since boot.
enum AppShortcutsActions {
    /// Matches FirstlightUI\AppShortcuts\Events\ActionsQueued in PHP.
    static let queuedEvent = "FirstlightUI\\AppShortcuts\\Events\\ActionsQueued"

    /// Matches RunActionCommand::RESULT_PREFIX in PHP.
    private static let resultPrefix = "app-shortcuts-result:"

    /// The oldest entries are dropped past this, so a queue nobody drains cannot grow without end.
    private static let limit = 100

    private static let lock = NSLock()
    private static let runLock = NSLock()

    struct Failed: Error, CustomLocalizedStringResourceConvertible {
        var localizedStringResource: LocalizedStringResource { "That didn't work. Open the app to try again." }
    }

    private static var file: URL {
        FileManager.default.urls(for: .applicationSupportDirectory, in: .userDomainMask)[0]
            .appendingPathComponent("firstlight-app-shortcuts", isDirectory: true)
            .appendingPathComponent("queued-actions.json")
    }

    /// Runs an action for its App Intent and returns what Siri should say: PHP's reply, else the
    /// configured one. Throws when the action's class threw, so Siri reports that it failed.
    static func perform(shortcut: String, reply: String?) async throws -> String {
        let id = UUID().uuidString.lowercased()
        let at = Int(Date().timeIntervalSince1970 * 1000)

        let output = await withCheckedContinuation { continuation in
            // Booting PHP blocks for a second or more: keep it off the main actor.
            DispatchQueue.global(qos: .userInitiated).async {
                continuation.resume(returning: runLock.withLock {
                    artisan("app-shortcuts:run --shortcut=\(shortcut) --id=\(id) --at=\(at)")
                })
            }
        }

        guard let output else {
            try enqueue(shortcut: shortcut, id: id, at: at)
            return reply ?? "Done."
        }

        guard let result = parse(output), result["ran"] as? Bool == true else {
            DebugLogger.shared.log("⚡️ app-shortcuts: \(shortcut) failed: \(output.suffix(300))")
            throw Failed()
        }

        return (result["reply"] as? String) ?? reply ?? "Done."
    }

    /// Runs an artisan command in the app's PHP through NativePHP's runArtisan, which boots PHP
    /// when the app is closed (unpacking the bundle if needed) or waits for the app's own boot.
    /// Nil when PHP is not available, so nothing ran.
    private static func artisan(_ command: String) -> String? {
        NativePHPApp.runArtisan(command, timeout: 10)
    }

    private static func parse(_ output: String) -> [String: Any]? {
        guard let line = output.split(whereSeparator: \.isNewline).last(where: { $0.hasPrefix(resultPrefix) }),
              let data = line.dropFirst(resultPrefix.count).data(using: .utf8) else {
            return nil
        }

        return try? JSONSerialization.jsonObject(with: data) as? [String: Any]
    }

    /// Queues one run of an action and wakes PHP when a native screen is live.
    static func enqueue(shortcut: String, id: String = UUID().uuidString.lowercased(), at: Int = Int(Date().timeIntervalSince1970 * 1000)) throws {
        try lock.withLock {
            var entries = read()
            entries.append(["id": id, "shortcut": shortcut, "at": at])

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
