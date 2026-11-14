import Foundation

// Stand-ins for NativePHP host types, used only by bin/typecheck-ios. Signatures copied from
// nativephp/mobile 4.7 resources/xcode/NativePHP/{Bridge/BridgeRouter,AppDelegate,DeepLinkRouter,DebugLogger,
// NativeRender/NativeElementBridge,Bridge/PersistentPHPRuntime,AppUpdateManager}.swift.
// Keep them in sync when upgrading NativePHP; a real device build is the final check.

protocol BridgeFunction {
    func execute(parameters: [String: Any]) throws -> [String: Any]
}

struct BridgeResponse {
    static func success(data: [String: Any] = [:]) -> [String: Any] {
        data
    }

    static func error(code: String, message: String, data: [String: Any] = [:]) -> [String: Any] {
        ["status": "error", "code": code, "message": message, "data": data]
    }
}

extension Notification.Name {
    static let didReceiveShortcutItem = Notification.Name("NativePHP.didReceiveShortcutItem")
}

final class DeepLinkRouter {
    static let shared = DeepLinkRouter()

    func handle(url: URL) {}
}

final class DebugLogger {
    static let shared = DebugLogger()

    func log(_ message: String) {}
}

final class NativeElementBridge {
    @discardableResult
    static func sendNativeEvent(eventName: String, payloadJson: String) -> Bool {
        false
    }
}

final class PersistentPHPRuntime {
    static let shared = PersistentPHPRuntime()

    private(set) var isBooted = false

    func boot() -> Bool {
        false
    }

    func artisan(command: String) -> String {
        ""
    }
}

class AppUpdateManager {
    static let shared = AppUpdateManager()

    func getAppPath() -> String {
        ""
    }
}
