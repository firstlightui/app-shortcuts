import UIKit

/// init_function: runs before the first scene connects, so it also sees the quick action that
/// launched the app (NativePHP posts it from its scene delegate).
func appShortcutsInit() {
    AppShortcutsRouter.start()
}

/// Opens a shortcut's path through NativePHP's deep-link router, which parks it until PHP is ready
/// on a cold launch and pushes the screen when the app is running. Route middleware, such as
/// firstlightui/app-guard's app-lock, runs as for any other navigation.
enum AppShortcutsRouter {
    /// Matches IosShortcuts::QUICK_ACTION_PREFIX in PHP.
    static let quickActionPrefix = "dev.firstlightui.app-shortcuts."

    private static var observer: NSObjectProtocol?

    static func start() {
        guard observer == nil else {
            return
        }

        observer = NotificationCenter.default.addObserver(forName: .didReceiveShortcutItem, object: nil, queue: .main) { notification in
            guard let item = notification.userInfo?["shortcutItem"] as? UIApplicationShortcutItem,
                  item.type.hasPrefix(quickActionPrefix) else {
                return
            }

            // A quick action always opens the app; an action runs on the screen already showing.
            if let action = item.userInfo?["action"] as? String {
                do {
                    try AppShortcutsActions.enqueue(shortcut: action)
                } catch {
                    DebugLogger.shared.log("⚡️ app-shortcuts: could not queue \(action): \(error)")
                }
            } else if let path = item.userInfo?["path"] as? String {
                open(path: path)
            }
        }
    }

    /// Opens an app path such as /tasks/create. A custom scheme with no host keeps the path as
    /// the route; the scheme itself is never registered, so no other app can open these links.
    static func open(path: String) {
        guard path.hasPrefix("/"), !path.hasPrefix("//"), let url = URL(string: "firstlight-shortcut://" + path) else {
            DebugLogger.shared.log("⚡️ app-shortcuts: ignoring path \(path)")
            return
        }

        DeepLinkRouter.shared.handle(url: url)
    }
}
