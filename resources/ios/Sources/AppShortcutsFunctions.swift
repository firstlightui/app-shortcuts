import UIKit

/// Bridge functions called from PHP through nativephp_call(). They run on the PHP thread, so the
/// quick-action list, which belongs to UIApplication, is changed on the main queue. Only quick
/// actions with this plugin's prefix are touched.
enum AppShortcutsFunctions {

    /// AppShortcuts.Push {"id", "title", "shortTitle", "path", "icon": string|null}
    class Push: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let id = parameters["id"] as? String, !id.isEmpty,
                  let title = parameters["title"] as? String, !title.isEmpty,
                  let path = parameters["path"] as? String, path.hasPrefix("/") else {
                return BridgeResponse.error(code: "INVALID_PARAMETERS", message: "id, title and an app path are required")
            }

            let icon = (parameters["icon"] as? String).map { UIApplicationShortcutIcon(systemImageName: $0) }
            let item = UIApplicationShortcutItem(
                type: AppShortcutsRouter.quickActionPrefix + id,
                localizedTitle: title,
                localizedSubtitle: nil,
                icon: icon,
                userInfo: ["path": path as NSString]
            )

            DispatchQueue.main.async {
                var items = UIApplication.shared.shortcutItems ?? []
                items.removeAll { $0.type == item.type }
                items.insert(item, at: 0)
                UIApplication.shared.shortcutItems = items
            }

            return BridgeResponse.success()
        }
    }

    /// AppShortcuts.Remove {"id"}
    class Remove: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let id = parameters["id"] as? String else {
                return BridgeResponse.error(code: "INVALID_PARAMETERS", message: "id is required")
            }

            let type = AppShortcutsRouter.quickActionPrefix + id

            DispatchQueue.main.async {
                UIApplication.shared.shortcutItems = (UIApplication.shared.shortcutItems ?? []).filter { $0.type != type }
            }

            return BridgeResponse.success()
        }
    }

    /// AppShortcuts.Clear {}
    class Clear: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            DispatchQueue.main.async {
                UIApplication.shared.shortcutItems = (UIApplication.shared.shortcutItems ?? []).filter {
                    !$0.type.hasPrefix(AppShortcutsRouter.quickActionPrefix)
                }
            }

            return BridgeResponse.success()
        }
    }
}
