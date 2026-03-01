import QtQuick
import QtQuick.Controls as QQC2
import QtQuick.Layouts
import Qt.labs.settings
import AppMesh

QQC2.ApplicationWindow {
    id: root
    visible: true
    title: currentMailboxName + " — AppMesh Mail"
    width: 1024
    height: 700

    property var mailboxes: []
    property var messages: []
    property var currentEmail: ({})
    property string currentMailbox: ""
    property string currentMailboxName: "Inbox"
    property bool connected: false
    property bool replyAboveQuote: true
    property string statusMessage: ""
    property string readingPanePosition: "right"  // "right", "bottom", "hidden"

    // --- Persist window geometry, split positions, credentials ---

    Settings {
        id: windowSettings
        category: "Window"
        property alias x: root.x
        property alias y: root.y
        property alias width: root.width
        property alias height: root.height
    }

    Settings {
        id: splitSettings
        category: "SplitView"
        property real folderWidth: 200
        property real messageWidth: 350
        property real messageHeight: 250
    }

    Settings {
        id: credSettings
        category: "Credentials"
        property string url: "https://mail.goldcoast.org:8443"
        property string user: ""
        property string pass: ""
        property bool remember: false
    }

    Settings {
        id: viewSettings
        category: "View"
        property alias readingPanePosition: root.readingPanePosition
    }

    // --- Status notification (replaces showPassiveNotification) ---

    function showNotification(msg) {
        statusMessage = msg
        notificationTimer.restart()
    }

    Timer {
        id: notificationTimer
        interval: 3000
        onTriggered: statusMessage = ""
    }

    // --- Helpers ---

    function parseDate(d) {
        if (!d) return null
        let date
        if (/^\d+$/.test(d)) {
            date = new Date(parseInt(d) * 1000)
        } else {
            date = new Date(d)
        }
        return isNaN(date.getTime()) ? null : date
    }

    function formatDate(d) {
        let date = parseDate(d)
        if (!date) return d || ""
        return date.toLocaleString(Qt.locale(), "ddd d MMM yyyy HH:mm")
    }

    function formatDateShort(d) {
        let date = parseDate(d)
        if (!date) return d || ""
        return date.toLocaleString(Qt.locale(), "d MMM HH:mm")
    }

    function quoteText(email) {
        let from = email.from || "someone"
        let date = formatDate(email.date)
        let body = email.body || email.preview || ""
        let header = "On " + date + ", " + from + " wrote:"
        let quoted = body.split("\n").map(function(line) { return "> " + line }).join("\n")
        return header + "\n" + quoted
    }

    function mailboxIcon(role) {
        let r = (role || "").toLowerCase()
        if (r.indexOf("inbox") >= 0) return "mail-folder-inbox"
        if (r.indexOf("sent") >= 0) return "mail-folder-sent"
        if (r.indexOf("draft") >= 0) return "mail-folder-drafts"
        if (r.indexOf("trash") >= 0) return "user-trash"
        if (r.indexOf("junk") >= 0 || r.indexOf("spam") >= 0) return "mail-mark-junk"
        if (r.indexOf("archive") >= 0) return "mail-folder-inbox"
        return "folder-mail"
    }

    // --- Connection & Data loading ---

    function connectAndLoad(url, user, pass) {
        let result = AppMeshBridge.portExecute("mail", "connect", {
            url: url, user: user, pass: pass
        })
        if (result.ok) {
            connected = true
            showNotification(result.ok)
            loadMailboxes()
            return true
        } else if (result.error) {
            showNotification("Connect failed: " + result.error.message)
            return false
        }
        return false
    }

    function checkStatus() {
        let result = AppMeshBridge.portExecute("mail", "status", {})
        if (result.ok && result.ok.indexOf("connected") === 0) {
            connected = true
            return true
        }
        return false
    }

    function loadMailboxes() {
        let result = AppMeshBridge.portExecute("mail", "mailboxes", {})
        if (result.ok) {
            mailboxes = result.ok
            for (let i = 0; i < mailboxes.length; i++) {
                if (mailboxes[i].name === "Inbox" || mailboxes[i].role === "Inbox") {
                    selectMailbox(mailboxes[i].id, mailboxes[i].name)
                    return
                }
            }
            if (mailboxes.length > 0) {
                selectMailbox(mailboxes[0].id, mailboxes[0].name)
            }
        } else if (result.error) {
            showNotification("Error: " + result.error.message)
        }
    }

    function selectMailbox(id, name) {
        currentMailbox = id
        currentMailboxName = name
        loadMessages()
    }

    function loadMessages() {
        if (!currentMailbox) return
        let result = AppMeshBridge.portExecute("mail", "query", {
            mailbox: currentMailbox, limit: "50"
        })
        if (result.ok) {
            messages = result.ok
            if (messages.length > 0 && !currentEmail.id) {
                readMessage(messages[0].id)
            }
        } else {
            messages = []
        }
    }

    function readMessage(id) {
        let result = AppMeshBridge.portExecute("mail", "read", { id: id })
        if (result.ok) {
            currentEmail = result.ok
        }
    }

    function sendMail(to, subject, body) {
        let result = AppMeshBridge.portExecute("mail", "send", {
            to: to, subject: subject, body: body
        })
        if (result.ok) {
            showNotification(result.ok)
            composeDialog.close()
        } else if (result.error) {
            showNotification("Error: " + result.error.message)
        }
    }

    function replyToMessage(id, body) {
        let result = AppMeshBridge.portExecute("mail", "reply", {
            id: id, body: body
        })
        if (result.ok) {
            showNotification(result.ok)
            replyDialog.close()
        } else if (result.error) {
            showNotification("Error: " + result.error.message)
        }
    }

    function deleteMessage(id) {
        let result = AppMeshBridge.portExecute("mail", "delete", { id: id })
        if (result.ok) {
            showNotification(result.ok)
            currentEmail = {}
            loadMessages()
        }
    }

    function flagMessage(id, flagged) {
        let cmd = flagged ? "unflag" : "flag"
        let result = AppMeshBridge.portExecute("mail", cmd, { id: id })
        if (result.ok) showNotification(result.ok)
    }

    function moveMessage(id, mailbox) {
        let result = AppMeshBridge.portExecute("mail", "move", {
            id: id, mailbox: mailbox
        })
        if (result.ok) {
            showNotification(result.ok)
            currentEmail = {}
            loadMessages()
        }
    }

    function searchMessages(text) {
        let result = AppMeshBridge.portExecute("mail", "search", { text: text })
        if (result.ok) {
            messages = result.ok
            currentMailboxName = "Search: " + text
        }
    }

    Component.onCompleted: {
        if (root.width < 100) root.width = 1024
        if (root.height < 100) root.height = 700

        if (AppMeshBridge.available) {
            if (checkStatus()) {
                loadMailboxes()
            } else if (credSettings.remember && credSettings.user && credSettings.pass) {
                if (connectAndLoad(credSettings.url, credSettings.user, credSettings.pass)) {
                    return
                }
                loginDialog.open()
            } else {
                loginDialog.open()
            }
        } else {
            loginDialog.open()
        }
    }

    // --- Menu Bar ---

    menuBar: QQC2.MenuBar {
        QQC2.Menu {
            title: "&File"
            QQC2.Action {
                text: "&Compose"
                icon.name: "mail-message-new"
                shortcut: "Ctrl+N"
                enabled: root.connected
                onTriggered: composeDialog.open()
            }
            QQC2.MenuSeparator {}
            QQC2.Action {
                text: "&Quit"
                icon.name: "application-exit"
                shortcut: "Ctrl+Q"
                onTriggered: Qt.quit()
            }
        }
        QQC2.Menu {
            title: "&Edit"
            QQC2.Action {
                text: "&Search"
                icon.name: "edit-find"
                shortcut: "Ctrl+F"
                enabled: root.connected
                onTriggered: searchField.forceActiveFocus()
            }
        }
        QQC2.Menu {
            title: "&View"
            QQC2.Action {
                text: "Reading Pane &Right"
                icon.name: "view-right-new"
                checkable: true
                checked: root.readingPanePosition === "right"
                onTriggered: root.readingPanePosition = "right"
            }
            QQC2.Action {
                text: "Reading Pane &Bottom"
                icon.name: "view-bottom-new"
                checkable: true
                checked: root.readingPanePosition === "bottom"
                onTriggered: root.readingPanePosition = "bottom"
            }
            QQC2.Action {
                text: "Reading Pane &Hidden"
                icon.name: "view-hidden"
                checkable: true
                checked: root.readingPanePosition === "hidden"
                onTriggered: root.readingPanePosition = "hidden"
            }
            QQC2.MenuSeparator {}
            QQC2.Action {
                text: "Re&fresh"
                icon.name: "view-refresh"
                shortcut: "F5"
                enabled: root.connected
                onTriggered: { loadMailboxes(); loadMessages() }
            }
        }
        QQC2.Menu {
            title: "&Account"
            QQC2.Action {
                text: root.connected ? "&Disconnect" : "&Connect"
                icon.name: root.connected ? "network-disconnect" : "network-connect"
                onTriggered: {
                    if (root.connected) {
                        root.connected = false
                        root.mailboxes = []
                        root.messages = []
                        root.currentMailbox = ""
                        root.currentMailboxName = "Inbox"
                        root.currentEmail = {}
                    }
                    loginDialog.open()
                }
            }
        }
    }

    // --- Main Toolbar ---

    header: QQC2.ToolBar {
        RowLayout {
            anchors.fill: parent
            anchors.leftMargin: 4
            anchors.rightMargin: 4

            QQC2.ToolButton {
                icon.name: "mail-message-new"
                text: "Compose"
                display: QQC2.AbstractButton.TextBesideIcon
                enabled: root.connected
                onClicked: composeDialog.open()
            }
            QQC2.ToolSeparator {}
            QQC2.ToolButton {
                icon.name: "mail-reply-sender"
                text: "Reply"
                display: QQC2.AbstractButton.TextBesideIcon
                enabled: !!root.currentEmail.id
                onClicked: {
                    replyDialog.replyToId = root.currentEmail.id || ""
                    replyDialog.replySubject = root.currentEmail.subject || ""
                    replyDialog.quotedText = root.quoteText(root.currentEmail)
                    replyDialog.prepareBody()
                    replyDialog.open()
                }
            }
            QQC2.ToolButton {
                icon.name: "mail-forward"
                text: "Forward"
                display: QQC2.AbstractButton.TextBesideIcon
                enabled: !!root.currentEmail.id
                onClicked: {
                    composeTo.text = ""
                    composeSubject.text = "Fwd: " + (root.currentEmail.subject || "")
                    composeBody.text = "\n\n--- Forwarded message ---\n" +
                        "From: " + (root.currentEmail.from || "") + "\n" +
                        "Date: " + root.formatDate(root.currentEmail.date) + "\n" +
                        "Subject: " + (root.currentEmail.subject || "") + "\n\n" +
                        (root.currentEmail.body || root.currentEmail.preview || "")
                    composeDialog.open()
                }
            }
            QQC2.ToolSeparator {}
            QQC2.ToolButton {
                icon.name: "flag"
                text: "Flag"
                display: QQC2.AbstractButton.TextBesideIcon
                enabled: !!root.currentEmail.id
                onClicked: root.flagMessage(root.currentEmail.id, false)
            }
            QQC2.ToolButton {
                icon.name: "edit-delete"
                text: "Delete"
                display: QQC2.AbstractButton.TextBesideIcon
                enabled: !!root.currentEmail.id
                onClicked: root.deleteMessage(root.currentEmail.id)
            }
            QQC2.ToolButton {
                icon.name: "edit-move"
                text: "Move"
                display: QQC2.AbstractButton.TextBesideIcon
                enabled: !!root.currentEmail.id
                onClicked: moveDialog.open()
            }

            Item { Layout.fillWidth: true }

            QQC2.TextField {
                id: searchField
                Layout.preferredWidth: 220
                placeholderText: "Search mail..."
                enabled: root.connected
                onAccepted: {
                    if (text.length > 0) searchMessages(text)
                }
            }
        }
    }

    // --- Three-pane SplitView ---

    QQC2.SplitView {
        id: outerSplit
        anchors.fill: parent
        orientation: Qt.Horizontal

        // --- Left pane: Folder list ---
        Rectangle {
            id: folderPane
            QQC2.SplitView.preferredWidth: splitSettings.folderWidth
            QQC2.SplitView.minimumWidth: 120
            QQC2.SplitView.maximumWidth: 400
            color: palette.window

            ColumnLayout {
                anchors.fill: parent
                spacing: 0

                Rectangle {
                    Layout.fillWidth: true
                    implicitHeight: folderHeader.implicitHeight + 8
                    color: palette.alternateBase

                    QQC2.Label {
                        id: folderHeader
                        text: "Mailboxes"
                        font.bold: true
                        anchors.fill: parent
                        anchors.margins: 4
                        verticalAlignment: Text.AlignVCenter
                    }
                }

                ListView {
                    id: folderList
                    Layout.fillWidth: true
                    Layout.fillHeight: true
                    model: root.mailboxes
                    clip: true
                    currentIndex: -1

                    delegate: QQC2.ItemDelegate {
                        required property var modelData
                        required property int index
                        width: ListView.view.width
                        highlighted: modelData.id === root.currentMailbox

                        contentItem: RowLayout {
                            spacing: 4

                            Image {
                                source: "image://icon/" + root.mailboxIcon(modelData.role)
                                sourceSize: Qt.size(16, 16)
                                Layout.preferredWidth: 16
                                Layout.preferredHeight: 16
                            }
                            QQC2.Label {
                                text: modelData.name || "Unknown"
                                elide: Text.ElideRight
                                Layout.fillWidth: true
                            }
                            QQC2.Label {
                                text: (modelData.unread || 0) > 0 ? String(modelData.unread) : ""
                                font.bold: true
                                opacity: 0.7
                                visible: (modelData.unread || 0) > 0
                            }
                        }

                        onClicked: root.selectMailbox(modelData.id, modelData.name)
                    }

                    // Empty state
                    ColumnLayout {
                        anchors.centerIn: parent
                        visible: folderList.count === 0
                        spacing: 8

                        Image {
                            source: "image://icon/" + (root.connected ? "folder-mail" : "network-disconnect")
                            sourceSize: Qt.size(48, 48)
                            Layout.alignment: Qt.AlignHCenter
                        }
                        QQC2.Label {
                            text: root.connected ? "No mailboxes" : "Not connected"
                            opacity: 0.6
                            Layout.alignment: Qt.AlignHCenter
                        }
                    }
                }
            }

            onWidthChanged: splitSettings.folderWidth = width
        }

        // --- Right area: message list + reading pane ---
        QQC2.SplitView {
            id: innerSplit
            QQC2.SplitView.fillWidth: true
            orientation: root.readingPanePosition === "bottom" ? Qt.Vertical : Qt.Horizontal

            // --- Message list pane ---
            Rectangle {
                id: messagePane
                QQC2.SplitView.preferredWidth: root.readingPanePosition === "right" ? splitSettings.messageWidth : undefined
                QQC2.SplitView.preferredHeight: root.readingPanePosition === "bottom" ? splitSettings.messageHeight : undefined
                QQC2.SplitView.minimumWidth: root.readingPanePosition === "right" ? 200 : undefined
                QQC2.SplitView.minimumHeight: root.readingPanePosition === "bottom" ? 120 : undefined
                QQC2.SplitView.fillWidth: root.readingPanePosition === "hidden"
                QQC2.SplitView.fillHeight: root.readingPanePosition === "hidden"
                color: palette.window

                ColumnLayout {
                    anchors.fill: parent
                    spacing: 0

                    // Column headers
                    Rectangle {
                        Layout.fillWidth: true
                        implicitHeight: columnHeaders.implicitHeight + 8
                        color: palette.alternateBase

                        Row {
                            id: columnHeaders
                            anchors.fill: parent
                            anchors.margins: 4
                            spacing: 4

                            QQC2.Label {
                                width: 180
                                text: "From"
                                font.bold: true
                                elide: Text.ElideRight
                            }
                            QQC2.Label {
                                width: parent.width - 180 - 90 - 8
                                text: "Subject"
                                font.bold: true
                                elide: Text.ElideRight
                            }
                            QQC2.Label {
                                width: 86
                                text: "Date"
                                font.bold: true
                                horizontalAlignment: Text.AlignRight
                            }
                        }
                    }

                    // Separator
                    Rectangle {
                        Layout.fillWidth: true
                        implicitHeight: 1
                        color: palette.mid
                    }

                    ListView {
                        id: messageList
                        Layout.fillWidth: true
                        Layout.fillHeight: true
                        model: root.messages
                        clip: true
                        currentIndex: -1

                        delegate: QQC2.ItemDelegate {
                            required property var modelData
                            required property int index
                            width: ListView.view.width
                            highlighted: (root.currentEmail.id || "") === modelData.id

                            contentItem: Row {
                                spacing: 4

                                QQC2.Label {
                                    width: 180
                                    text: modelData.from || "Unknown"
                                    font.bold: !(modelData.isRead)
                                    elide: Text.ElideRight
                                    anchors.verticalCenter: parent.verticalCenter
                                }
                                QQC2.Label {
                                    width: parent.width - 180 - 90 - 8
                                    text: modelData.subject || "(no subject)"
                                    elide: Text.ElideRight
                                    anchors.verticalCenter: parent.verticalCenter
                                }
                                QQC2.Label {
                                    width: 86
                                    text: root.formatDateShort(modelData.date)
                                    opacity: 0.7
                                    horizontalAlignment: Text.AlignRight
                                    anchors.verticalCenter: parent.verticalCenter

                                    QQC2.ToolTip.text: root.formatDate(modelData.date)
                                    QQC2.ToolTip.visible: dateHover.containsMouse
                                    MouseArea {
                                        id: dateHover
                                        anchors.fill: parent
                                        hoverEnabled: true
                                        acceptedButtons: Qt.NoButton
                                    }
                                }
                            }

                            onClicked: root.readMessage(modelData.id)
                        }

                        // Empty state
                        ColumnLayout {
                            anchors.centerIn: parent
                            visible: messageList.count === 0
                            spacing: 8

                            Image {
                                source: "image://icon/" + (root.connected ? "mail-unread" : "network-disconnect")
                                sourceSize: Qt.size(48, 48)
                                Layout.alignment: Qt.AlignHCenter
                            }
                            QQC2.Label {
                                text: {
                                    if (!AppMeshBridge.available) return "Mail library not loaded"
                                    if (!root.connected) return "Not connected"
                                    return "No messages"
                                }
                                opacity: 0.6
                                Layout.alignment: Qt.AlignHCenter
                            }
                        }
                    }
                }

                onWidthChanged: if (root.readingPanePosition === "right") splitSettings.messageWidth = width
                onHeightChanged: if (root.readingPanePosition === "bottom") splitSettings.messageHeight = height
            }

            // --- Reading pane ---
            Rectangle {
                id: detailPane
                visible: root.readingPanePosition !== "hidden"
                QQC2.SplitView.fillWidth: true
                QQC2.SplitView.fillHeight: true
                QQC2.SplitView.minimumWidth: root.readingPanePosition === "right" ? 250 : undefined
                QQC2.SplitView.minimumHeight: root.readingPanePosition === "bottom" ? 120 : undefined
                color: palette.base

                ColumnLayout {
                    anchors.fill: parent
                    spacing: 0

                    // Detail toolbar
                    QQC2.ToolBar {
                        Layout.fillWidth: true
                        visible: !!root.currentEmail.id

                        RowLayout {
                            anchors.fill: parent
                            anchors.leftMargin: 4
                            anchors.rightMargin: 4

                            QQC2.ToolButton {
                                icon.name: "mail-reply-sender"
                                text: "Reply"
                                display: QQC2.AbstractButton.TextBesideIcon
                                onClicked: {
                                    replyDialog.replyToId = root.currentEmail.id || ""
                                    replyDialog.replySubject = root.currentEmail.subject || ""
                                    replyDialog.quotedText = root.quoteText(root.currentEmail)
                                    replyDialog.prepareBody()
                                    replyDialog.open()
                                }
                            }
                            QQC2.ToolButton {
                                icon.name: "flag"
                                display: QQC2.AbstractButton.IconOnly
                                QQC2.ToolTip.text: "Flag"
                                QQC2.ToolTip.visible: hovered
                                onClicked: root.flagMessage(root.currentEmail.id, false)
                            }
                            QQC2.ToolButton {
                                icon.name: "mail-mark-unread"
                                display: QQC2.AbstractButton.IconOnly
                                QQC2.ToolTip.text: "Mark Unread"
                                QQC2.ToolTip.visible: hovered
                                onClicked: {
                                    let result = AppMeshBridge.portExecute("mail", "mark_unread", { id: root.currentEmail.id })
                                    if (result.ok) root.showNotification(result.ok)
                                }
                            }
                            QQC2.ToolButton {
                                icon.name: "edit-move"
                                display: QQC2.AbstractButton.IconOnly
                                QQC2.ToolTip.text: "Move to..."
                                QQC2.ToolTip.visible: hovered
                                onClicked: moveDialog.open()
                            }
                            QQC2.ToolButton {
                                icon.name: "edit-delete"
                                display: QQC2.AbstractButton.IconOnly
                                QQC2.ToolTip.text: "Delete"
                                QQC2.ToolTip.visible: hovered
                                onClicked: root.deleteMessage(root.currentEmail.id)
                            }
                        }
                    }

                    // Detail content
                    QQC2.ScrollView {
                        Layout.fillWidth: true
                        Layout.fillHeight: true
                        visible: !!root.currentEmail.id

                        Flickable {
                            contentWidth: availableWidth
                            contentHeight: detailColumn.implicitHeight

                            ColumnLayout {
                                id: detailColumn
                                width: parent.width
                                spacing: 8

                                Item { Layout.preferredHeight: 4 }

                                GridLayout {
                                    columns: 2
                                    columnSpacing: 4
                                    rowSpacing: 4
                                    Layout.fillWidth: true
                                    Layout.leftMargin: 8
                                    Layout.rightMargin: 8

                                    QQC2.Label { text: "From:"; font.bold: true; opacity: 0.7 }
                                    QQC2.Label { text: root.currentEmail.from || ""; wrapMode: Text.Wrap; Layout.fillWidth: true }

                                    QQC2.Label { text: "To:"; font.bold: true; opacity: 0.7 }
                                    QQC2.Label { text: root.currentEmail.to || ""; wrapMode: Text.Wrap; Layout.fillWidth: true }

                                    QQC2.Label { text: "Date:"; font.bold: true; opacity: 0.7 }
                                    QQC2.Label { text: root.formatDate(root.currentEmail.date); Layout.fillWidth: true }

                                    QQC2.Label { text: "Subject:"; font.bold: true; opacity: 0.7 }
                                    QQC2.Label { text: root.currentEmail.subject || ""; font.bold: true; wrapMode: Text.Wrap; Layout.fillWidth: true }
                                }

                                // Separator
                                Rectangle {
                                    Layout.fillWidth: true
                                    Layout.leftMargin: 8
                                    Layout.rightMargin: 8
                                    implicitHeight: 1
                                    color: palette.mid
                                }

                                QQC2.Label {
                                    text: root.currentEmail.body || root.currentEmail.preview || ""
                                    wrapMode: Text.Wrap
                                    Layout.fillWidth: true
                                    Layout.leftMargin: 8
                                    Layout.rightMargin: 8
                                    textFormat: Text.MarkdownText
                                    onLinkActivated: function(link) { Qt.openUrlExternally(link) }
                                }

                                Item { Layout.preferredHeight: 8 }
                            }
                        }
                    }

                    // Empty state
                    ColumnLayout {
                        anchors.centerIn: parent
                        visible: !root.currentEmail.id
                        spacing: 8

                        Image {
                            source: "image://icon/mail-read"
                            sourceSize: Qt.size(48, 48)
                            Layout.alignment: Qt.AlignHCenter
                        }
                        QQC2.Label {
                            text: "Select a message to read"
                            opacity: 0.6
                            Layout.alignment: Qt.AlignHCenter
                        }
                    }
                }
            }
        }
    }

    // --- Status Bar ---

    footer: QQC2.ToolBar {
        implicitHeight: 28

        RowLayout {
            anchors.fill: parent
            anchors.leftMargin: 8
            anchors.rightMargin: 8
            spacing: 8

            // Connection indicator
            Rectangle {
                width: 8
                height: 8
                radius: 4
                color: root.connected ? "#2ecc71" : "#e74c3c"
                Layout.alignment: Qt.AlignVCenter
            }
            QQC2.Label {
                text: root.connected ? "Connected" : "Disconnected"
                font.pointSize: Qt.application.font.pointSize - 1
                opacity: 0.7
            }

            // Separator
            Rectangle {
                width: 1
                Layout.fillHeight: true
                Layout.topMargin: 4
                Layout.bottomMargin: 4
                color: palette.mid
            }

            QQC2.Label {
                text: root.currentMailboxName
                font.pointSize: Qt.application.font.pointSize - 1
            }

            Rectangle {
                width: 1
                Layout.fillHeight: true
                Layout.topMargin: 4
                Layout.bottomMargin: 4
                color: palette.mid
            }

            QQC2.Label {
                text: root.messages.length + " messages"
                font.pointSize: Qt.application.font.pointSize - 1
                opacity: 0.7
            }

            Item { Layout.fillWidth: true }

            // Status notification area
            QQC2.Label {
                text: root.statusMessage
                font.pointSize: Qt.application.font.pointSize - 1
                opacity: root.statusMessage.length > 0 ? 1.0 : 0.0
                color: palette.highlight

                Behavior on opacity {
                    NumberAnimation { duration: 200 }
                }
            }
        }
    }

    // --- Login Dialog ---

    QQC2.Dialog {
        id: loginDialog
        title: "Connect to Mail Server"
        modal: true
        anchors.centerIn: parent
        width: 400
        closePolicy: root.connected ? QQC2.Popup.CloseOnEscape : QQC2.Popup.NoAutoClose
        standardButtons: QQC2.Dialog.NoButton

        ColumnLayout {
            anchors.fill: parent
            spacing: 8

            Image {
                source: "image://icon/mail-client"
                sourceSize: Qt.size(48, 48)
                Layout.alignment: Qt.AlignHCenter
            }

            QQC2.Label {
                text: "Enter your JMAP server credentials"
                Layout.alignment: Qt.AlignHCenter
                opacity: 0.7
            }

            QQC2.TextField {
                id: loginUrl
                Layout.fillWidth: true
                placeholderText: "JMAP Server URL"
                text: credSettings.url
            }
            QQC2.TextField {
                id: loginUser
                Layout.fillWidth: true
                placeholderText: "Email / Username"
                text: credSettings.remember ? credSettings.user : ""
            }
            QQC2.TextField {
                id: loginPass
                Layout.fillWidth: true
                placeholderText: "Password"
                echoMode: TextInput.Password
                text: credSettings.remember ? credSettings.pass : ""
                onAccepted: loginConnectAction.trigger()
            }

            QQC2.CheckBox {
                id: rememberCheck
                text: "Remember me"
                checked: credSettings.remember
            }

            QQC2.Label {
                id: loginError
                text: "Connection failed — check URL and credentials"
                color: "red"
                visible: false
                Layout.alignment: Qt.AlignHCenter
            }

            RowLayout {
                Layout.alignment: Qt.AlignRight
                spacing: 8

                QQC2.Button {
                    text: "Cancel"
                    icon.name: "dialog-cancel"
                    visible: root.connected
                    onClicked: loginDialog.close()
                }
                QQC2.Button {
                    id: loginConnectBtn
                    text: "Connect"
                    icon.name: "network-connect"
                    highlighted: true

                    QQC2.Action {
                        id: loginConnectAction
                        onTriggered: {
                            loginError.visible = false
                            let ok = root.connectAndLoad(loginUrl.text, loginUser.text, loginPass.text)
                            if (ok) {
                                if (rememberCheck.checked) {
                                    credSettings.url = loginUrl.text
                                    credSettings.user = loginUser.text
                                    credSettings.pass = loginPass.text
                                    credSettings.remember = true
                                } else {
                                    credSettings.user = ""
                                    credSettings.pass = ""
                                    credSettings.remember = false
                                }
                                loginDialog.close()
                            } else {
                                loginError.visible = true
                            }
                        }
                    }

                    onClicked: loginConnectAction.trigger()
                }
            }
        }
    }

    // --- Compose Dialog ---

    QQC2.Dialog {
        id: composeDialog
        title: "Compose"
        modal: true
        anchors.centerIn: parent
        width: 550
        height: 450
        standardButtons: QQC2.Dialog.NoButton

        ColumnLayout {
            anchors.fill: parent
            spacing: 8

            QQC2.TextField {
                id: composeTo
                Layout.fillWidth: true
                placeholderText: "To"
            }
            QQC2.TextField {
                id: composeSubject
                Layout.fillWidth: true
                placeholderText: "Subject"
            }
            QQC2.ScrollView {
                Layout.fillWidth: true
                Layout.fillHeight: true

                QQC2.TextArea {
                    id: composeBody
                    placeholderText: "Message body..."
                    wrapMode: TextEdit.Wrap
                }
            }

            RowLayout {
                Layout.alignment: Qt.AlignRight
                spacing: 8

                QQC2.Button {
                    text: "Cancel"
                    icon.name: "dialog-cancel"
                    onClicked: composeDialog.close()
                }
                QQC2.Button {
                    text: "Send"
                    icon.name: "mail-send"
                    highlighted: true
                    onClicked: root.sendMail(composeTo.text, composeSubject.text, composeBody.text)
                }
            }
        }

        onClosed: {
            composeTo.text = ""
            composeSubject.text = ""
            composeBody.text = ""
        }
    }

    // --- Reply Dialog ---

    QQC2.Dialog {
        id: replyDialog
        title: "Reply"
        modal: true
        anchors.centerIn: parent
        width: 600
        height: 500
        standardButtons: QQC2.Dialog.NoButton

        property string replyToId: ""
        property string replySubject: ""
        property string quotedText: ""

        function prepareBody() {
            if (root.replyAboveQuote) {
                replyBody.text = "\n\n" + quotedText
                replyBody.cursorPosition = 0
            } else {
                replyBody.text = quotedText + "\n\n"
                replyBody.cursorPosition = replyBody.text.length
            }
        }

        ColumnLayout {
            anchors.fill: parent
            spacing: 8

            RowLayout {
                Layout.fillWidth: true

                QQC2.Label {
                    text: "Re: " + replyDialog.replySubject
                    font.bold: true
                    elide: Text.ElideRight
                    Layout.fillWidth: true
                }

                QQC2.Label {
                    text: "Reply position:"
                    opacity: 0.7
                }
                QQC2.ComboBox {
                    id: replyPosition
                    model: ["Above quote", "Below quote"]
                    currentIndex: root.replyAboveQuote ? 0 : 1
                    onCurrentIndexChanged: {
                        root.replyAboveQuote = (currentIndex === 0)
                        replyDialog.prepareBody()
                    }
                }
            }

            QQC2.ScrollView {
                Layout.fillWidth: true
                Layout.fillHeight: true

                QQC2.TextArea {
                    id: replyBody
                    wrapMode: TextEdit.Wrap
                    font.family: "monospace"
                }
            }

            RowLayout {
                Layout.alignment: Qt.AlignRight
                spacing: 8

                QQC2.Button {
                    text: "Cancel"
                    icon.name: "dialog-cancel"
                    onClicked: replyDialog.close()
                }
                QQC2.Button {
                    text: "Send Reply"
                    icon.name: "mail-reply-sender"
                    highlighted: true
                    onClicked: root.replyToMessage(replyDialog.replyToId, replyBody.text)
                }
            }
        }

        onClosed: replyBody.text = ""
    }

    // --- Move Dialog ---

    QQC2.Dialog {
        id: moveDialog
        title: "Move to..."
        modal: true
        anchors.centerIn: parent
        width: 300
        height: 400
        standardButtons: QQC2.Dialog.Cancel

        ListView {
            anchors.fill: parent
            model: root.mailboxes
            clip: true

            delegate: QQC2.ItemDelegate {
                required property var modelData
                width: ListView.view.width
                text: modelData.name || "Unknown"
                icon.name: "folder-mail"
                onClicked: {
                    root.moveMessage(root.currentEmail.id, modelData.name)
                    moveDialog.close()
                }
            }
        }
    }
}
