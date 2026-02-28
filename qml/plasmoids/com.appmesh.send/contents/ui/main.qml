import QtQuick
import QtQuick.Layouts
import org.kde.plasma.plasmoid
import org.kde.plasma.components as PC3
import org.kde.kirigami as Kirigami
import AppMesh

PlasmoidItem {
    id: root

    Plasmoid.icon: "mail-send"
    toolTipMainText: "Mesh Send"
    toolTipSubText: AppMeshBridge.available ? "Connected" : "Library not loaded"

    compactRepresentation: Kirigami.Icon {
        source: Plasmoid.icon
        active: compactMouse.containsMouse
        MouseArea {
            id: compactMouse
            anchors.fill: parent
            hoverEnabled: true
            onClicked: root.expanded = !root.expanded
        }
    }

    fullRepresentation: ColumnLayout {
        Layout.preferredWidth: Kirigami.Units.gridUnit * 18
        Layout.preferredHeight: Kirigami.Units.gridUnit * 10
        spacing: Kirigami.Units.smallSpacing

        PC3.Label {
            text: AppMeshBridge.available ? "AppMesh connected" : "Library not loaded"
            color: AppMeshBridge.available ? Kirigami.Theme.positiveTextColor : Kirigami.Theme.negativeTextColor
            font.bold: true
            Layout.fillWidth: true
        }

        PC3.TextField {
            id: msgField
            placeholderText: "Type a message..."
            text: "Hello from Mesh Send!"
            Layout.fillWidth: true
            onAccepted: broadcastBtn.clicked()
        }

        RowLayout {
            spacing: Kirigami.Units.smallSpacing

            PC3.Button {
                id: broadcastBtn
                icon.name: "document-send"
                text: "Broadcast"
                onClicked: {
                    if (msgField.text.length > 0)
                        AppMeshBridge.sendMessage("chat", msgField.text)
                }
            }

            PC3.Button {
                icon.name: "notifications"
                text: "Notify"
                onClicked: {
                    if (msgField.text.length > 0) {
                        AppMeshBridge.portExecute("notify", "send", {
                            summary: "Mesh Send",
                            body: msgField.text
                        })
                        AppMeshBridge.sendMessage("chat", msgField.text)
                    }
                }
            }
        }
    }
}
