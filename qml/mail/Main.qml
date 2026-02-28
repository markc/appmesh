import QtQuick
import QtQuick.Controls as QQC2
import QtQuick.Layouts
import Qt.labs.settings
import org.kde.kirigami as Kirigami
import AppMesh

Kirigami.ApplicationWindow {
    id: root
    visible: true
    title: currentMailboxName + " — AppMesh Mail"

    property var mailboxes: []
    property var messages: []
    property var currentEmail: ({})
    property string currentMailbox: ""
    property string currentMailboxName: "Inbox"
    property bool connected: false
    property bool replyAboveQuote: true

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
    }

    Settings {
        id: credSettings
        category: "Credentials"
        property string url: "https://mail.goldcoast.org:8443"
        property string user: ""
        property string pass: ""
        property bool remember: false
    }

    // --- Helpers ---

    function formatDate(d) {
        if (!d) return ""
        let date
        if (/^\d+$/.test(d)) {
            date = new Date(parseInt(d) * 1000)
        } else {
            date = new Date(d)
        }
        if (isNaN(date.getTime())) return d
        return date.toLocaleString(Qt.locale(), "ddd d MMM yyyy HH:mm")
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
            showPassiveNotification(result.ok)
            loadMailboxes()
            return true
        } else if (result.error) {
            showPassiveNotification("Connect failed: " + result.error.message)
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
            showPassiveNotification("Error: " + result.error.message)
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
            // Auto-select first message so detail pane is populated at startup
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
            showPassiveNotification(result.ok)
            composeSheet.close()
        } else if (result.error) {
            showPassiveNotification("Error: " + result.error.message)
        }
    }

    function replyToMessage(id, body) {
        let result = AppMeshBridge.portExecute("mail", "reply", {
            id: id, body: body
        })
        if (result.ok) {
            showPassiveNotification(result.ok)
            replySheet.close()
        } else if (result.error) {
            showPassiveNotification("Error: " + result.error.message)
        }
    }

    function deleteMessage(id) {
        let result = AppMeshBridge.portExecute("mail", "delete", { id: id })
        if (result.ok) {
            showPassiveNotification(result.ok)
            currentEmail = {}
            loadMessages()
        }
    }

    function flagMessage(id, flagged) {
        let cmd = flagged ? "unflag" : "flag"
        let result = AppMeshBridge.portExecute("mail", cmd, { id: id })
        if (result.ok) showPassiveNotification(result.ok)
    }

    function moveMessage(id, mailbox) {
        let result = AppMeshBridge.portExecute("mail", "move", {
            id: id, mailbox: mailbox
        })
        if (result.ok) {
            showPassiveNotification(result.ok)
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
        // Restore window size (defaults for first run)
        if (root.width < 100) root.width = 1024
        if (root.height < 100) root.height = 700

        if (AppMeshBridge.available) {
            if (checkStatus()) {
                loadMailboxes()
            } else if (credSettings.remember && credSettings.user && credSettings.pass) {
                // Auto-login with saved credentials
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

    // --- Toolbar ---

    header: QQC2.ToolBar {
        RowLayout {
            anchors.fill: parent
            anchors.leftMargin: Kirigami.Units.smallSpacing
            anchors.rightMargin: Kirigami.Units.smallSpacing

            QQC2.ToolButton {
                icon.name: "mail-message-new"
                text: "Compose"
                display: QQC2.AbstractButton.TextBesideIcon
                enabled: root.connected
                onClicked: composeSheet.open()
            }
            QQC2.ToolButton {
                icon.name: "view-refresh"
                text: "Refresh"
                display: QQC2.AbstractButton.TextBesideIcon
                enabled: root.connected
                onClicked: { loadMailboxes(); loadMessages() }
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

            QQC2.ToolButton {
                icon.name: root.connected ? "system-log-out" : "network-connect"
                text: root.connected ? "Log out" : "Log in"
                display: QQC2.AbstractButton.TextBesideIcon
                onClicked: {
                    if (root.connected) {
                        root.connected = false
                        root.mailboxes = []
                        root.messages = []
                        root.currentMailbox = ""
                        root.currentMailboxName = "Inbox"
                        root.currentEmail = {}
                        loginDialog.open()
                    } else {
                        loginDialog.open()
                    }
                }
            }
        }
    }

    // --- Three-pane SplitView ---

    QQC2.SplitView {
        id: splitView
        anchors.fill: parent
        orientation: Qt.Horizontal

        // --- Left pane: Folder list ---
        Rectangle {
            id: folderPane
            QQC2.SplitView.preferredWidth: splitSettings.folderWidth
            QQC2.SplitView.minimumWidth: 120
            QQC2.SplitView.maximumWidth: 400
            color: Kirigami.Theme.backgroundColor

            ColumnLayout {
                anchors.fill: parent
                spacing: 0

                QQC2.Label {
                    text: "Mailboxes"
                    font.bold: true
                    padding: Kirigami.Units.smallSpacing
                    Layout.fillWidth: true
                    background: Rectangle {
                        color: Kirigami.Theme.alternateBackgroundColor
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
                            spacing: Kirigami.Units.smallSpacing

                            Kirigami.Icon {
                                source: root.mailboxIcon(modelData.role)
                                Layout.preferredWidth: Kirigami.Units.iconSizes.small
                                Layout.preferredHeight: Kirigami.Units.iconSizes.small
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

                    Kirigami.PlaceholderMessage {
                        anchors.centerIn: parent
                        visible: folderList.count === 0
                        text: root.connected ? "No mailboxes" : "Not connected"
                        icon.name: root.connected ? "folder-mail" : "network-disconnect"
                    }
                }
            }

            // Save width when handle is dragged
            onWidthChanged: splitSettings.folderWidth = width
        }

        // --- Middle pane: Message list ---
        Rectangle {
            id: messagePane
            QQC2.SplitView.preferredWidth: splitSettings.messageWidth
            QQC2.SplitView.minimumWidth: 200
            QQC2.SplitView.maximumWidth: 800
            color: Kirigami.Theme.backgroundColor

            ColumnLayout {
                anchors.fill: parent
                spacing: 0

                QQC2.Label {
                    text: root.currentMailboxName
                    font.bold: true
                    padding: Kirigami.Units.smallSpacing
                    Layout.fillWidth: true
                    background: Rectangle {
                        color: Kirigami.Theme.alternateBackgroundColor
                    }
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

                        contentItem: ColumnLayout {
                            spacing: 2

                            RowLayout {
                                Layout.fillWidth: true
                                QQC2.Label {
                                    text: modelData.from || "Unknown"
                                    font.bold: true
                                    elide: Text.ElideRight
                                    Layout.fillWidth: true
                                }
                                QQC2.Label {
                                    text: root.formatDate(modelData.date)
                                    font.pointSize: Kirigami.Theme.smallFont.pointSize
                                    opacity: 0.7
                                }
                            }
                            QQC2.Label {
                                text: modelData.subject || "(no subject)"
                                elide: Text.ElideRight
                                Layout.fillWidth: true
                            }
                            QQC2.Label {
                                text: modelData.preview || ""
                                elide: Text.ElideRight
                                Layout.fillWidth: true
                                opacity: 0.6
                                font.pointSize: Kirigami.Theme.smallFont.pointSize
                            }
                        }

                        onClicked: root.readMessage(modelData.id)
                    }

                    Kirigami.PlaceholderMessage {
                        anchors.centerIn: parent
                        visible: messageList.count === 0
                        text: {
                            if (!AppMeshBridge.available) return "Mail library not loaded"
                            if (!root.connected) return "Not connected"
                            return "No messages"
                        }
                        icon.name: root.connected ? "mail-unread" : "network-disconnect"
                    }
                }
            }

            onWidthChanged: splitSettings.messageWidth = width
        }

        // --- Right pane: Message detail ---
        Rectangle {
            id: detailPane
            QQC2.SplitView.fillWidth: true
            QQC2.SplitView.minimumWidth: 250
            color: Kirigami.Theme.backgroundColor

            ColumnLayout {
                anchors.fill: parent
                spacing: 0

                // Detail toolbar
                QQC2.ToolBar {
                    Layout.fillWidth: true
                    visible: !!root.currentEmail.id

                    RowLayout {
                        anchors.fill: parent
                        anchors.leftMargin: Kirigami.Units.smallSpacing
                        anchors.rightMargin: Kirigami.Units.smallSpacing

                        QQC2.ToolButton {
                            icon.name: "mail-reply-sender"
                            text: "Reply"
                            display: QQC2.AbstractButton.TextBesideIcon
                            onClicked: {
                                replySheet.replyToId = root.currentEmail.id || ""
                                replySheet.replySubject = root.currentEmail.subject || ""
                                replySheet.quotedText = root.quoteText(root.currentEmail)
                                replySheet.prepareBody()
                                replySheet.open()
                            }
                        }
                        QQC2.ToolButton {
                            icon.name: "flag"
                            text: "Flag"
                            display: QQC2.AbstractButton.IconOnly
                            QQC2.ToolTip.text: "Flag"
                            QQC2.ToolTip.visible: hovered
                            onClicked: root.flagMessage(root.currentEmail.id, false)
                        }
                        QQC2.ToolButton {
                            icon.name: "mail-mark-unread"
                            text: "Unread"
                            display: QQC2.AbstractButton.IconOnly
                            QQC2.ToolTip.text: "Mark Unread"
                            QQC2.ToolTip.visible: hovered
                            onClicked: {
                                let result = AppMeshBridge.portExecute("mail", "mark_unread", { id: root.currentEmail.id })
                                if (result.ok) root.showPassiveNotification(result.ok)
                            }
                        }
                        QQC2.ToolButton {
                            icon.name: "edit-move"
                            text: "Move"
                            display: QQC2.AbstractButton.IconOnly
                            QQC2.ToolTip.text: "Move to..."
                            QQC2.ToolTip.visible: hovered
                            onClicked: moveSheet.open()
                        }
                        QQC2.ToolButton {
                            icon.name: "edit-delete"
                            text: "Delete"
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
                            spacing: Kirigami.Units.largeSpacing

                            Item { Layout.preferredHeight: Kirigami.Units.smallSpacing }

                            GridLayout {
                                columns: 2
                                columnSpacing: Kirigami.Units.smallSpacing
                                rowSpacing: 4
                                Layout.fillWidth: true
                                Layout.leftMargin: Kirigami.Units.largeSpacing
                                Layout.rightMargin: Kirigami.Units.largeSpacing

                                QQC2.Label { text: "From:"; font.bold: true; opacity: 0.7 }
                                QQC2.Label { text: root.currentEmail.from || ""; wrapMode: Text.Wrap; Layout.fillWidth: true }

                                QQC2.Label { text: "To:"; font.bold: true; opacity: 0.7 }
                                QQC2.Label { text: root.currentEmail.to || ""; wrapMode: Text.Wrap; Layout.fillWidth: true }

                                QQC2.Label { text: "Date:"; font.bold: true; opacity: 0.7 }
                                QQC2.Label { text: root.formatDate(root.currentEmail.date); Layout.fillWidth: true }

                                QQC2.Label { text: "Subject:"; font.bold: true; opacity: 0.7 }
                                QQC2.Label { text: root.currentEmail.subject || ""; font.bold: true; wrapMode: Text.Wrap; Layout.fillWidth: true }
                            }

                            Kirigami.Separator {
                                Layout.fillWidth: true
                                Layout.leftMargin: Kirigami.Units.largeSpacing
                                Layout.rightMargin: Kirigami.Units.largeSpacing
                            }

                            QQC2.Label {
                                text: root.currentEmail.body || root.currentEmail.preview || ""
                                wrapMode: Text.Wrap
                                Layout.fillWidth: true
                                Layout.leftMargin: Kirigami.Units.largeSpacing
                                Layout.rightMargin: Kirigami.Units.largeSpacing
                                textFormat: Text.PlainText
                            }

                            Item { Layout.preferredHeight: Kirigami.Units.largeSpacing }
                        }
                    }
                }

                // Empty state
                Kirigami.PlaceholderMessage {
                    anchors.centerIn: parent
                    visible: !root.currentEmail.id
                    text: "Select a message to read"
                    icon.name: "mail-read"
                }
            }
        }
    }

    // --- Login Dialog ---

    Kirigami.Dialog {
        id: loginDialog
        title: "Connect to Mail Server"
        preferredWidth: Kirigami.Units.gridUnit * 25
        standardButtons: Kirigami.Dialog.NoButton
        closePolicy: root.connected ? Kirigami.Dialog.CloseOnEscape : Kirigami.Dialog.NoAutoClose

        customFooterActions: [
            Kirigami.Action {
                text: "Connect"
                icon.name: "network-connect"
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
        ]

        ColumnLayout {
            spacing: Kirigami.Units.smallSpacing

            Kirigami.Icon {
                source: "mail-client"
                Layout.preferredWidth: Kirigami.Units.iconSizes.huge
                Layout.preferredHeight: Kirigami.Units.iconSizes.huge
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
                onAccepted: {
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

            QQC2.CheckBox {
                id: rememberCheck
                text: "Remember me"
                checked: credSettings.remember
            }

            QQC2.Label {
                id: loginError
                text: "Connection failed — check URL and credentials"
                color: Kirigami.Theme.negativeTextColor
                visible: false
                Layout.alignment: Qt.AlignHCenter
            }
        }
    }

    // --- Compose Sheet ---

    Kirigami.Dialog {
        id: composeSheet
        title: "Compose"
        preferredWidth: Kirigami.Units.gridUnit * 30
        preferredHeight: Kirigami.Units.gridUnit * 25
        standardButtons: Kirigami.Dialog.NoButton

        customFooterActions: [
            Kirigami.Action {
                text: "Send"
                icon.name: "mail-send"
                onTriggered: root.sendMail(composeTo.text, composeSubject.text, composeBody.text)
            },
            Kirigami.Action {
                text: "Cancel"
                icon.name: "dialog-cancel"
                onTriggered: composeSheet.close()
            }
        ]

        ColumnLayout {
            spacing: Kirigami.Units.smallSpacing

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
        }

        onClosed: {
            composeTo.text = ""
            composeSubject.text = ""
            composeBody.text = ""
        }
    }

    // --- Reply Sheet ---

    Kirigami.Dialog {
        id: replySheet
        title: "Reply"
        preferredWidth: Kirigami.Units.gridUnit * 35
        preferredHeight: Kirigami.Units.gridUnit * 28
        standardButtons: Kirigami.Dialog.NoButton

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

        customFooterActions: [
            Kirigami.Action {
                text: "Send Reply"
                icon.name: "mail-reply-sender"
                onTriggered: root.replyToMessage(replySheet.replyToId, replyBody.text)
            },
            Kirigami.Action {
                text: "Cancel"
                icon.name: "dialog-cancel"
                onTriggered: replySheet.close()
            }
        ]

        ColumnLayout {
            spacing: Kirigami.Units.smallSpacing

            RowLayout {
                Layout.fillWidth: true

                QQC2.Label {
                    text: "Re: " + replySheet.replySubject
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
                        replySheet.prepareBody()
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
        }

        onClosed: replyBody.text = ""
    }

    // --- Move Sheet ---

    Kirigami.Dialog {
        id: moveSheet
        title: "Move to..."
        preferredWidth: Kirigami.Units.gridUnit * 20
        standardButtons: Kirigami.Dialog.Cancel

        ListView {
            implicitHeight: Kirigami.Units.gridUnit * 15
            model: root.mailboxes

            delegate: QQC2.ItemDelegate {
                required property var modelData
                width: ListView.view.width
                text: modelData.name || "Unknown"
                icon.name: "folder-mail"
                onClicked: {
                    root.moveMessage(root.currentEmail.id, modelData.name)
                    moveSheet.close()
                }
            }
        }
    }
}
