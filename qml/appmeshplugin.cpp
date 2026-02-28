#include "appmeshplugin.h"
#include <QJsonDocument>
#include <QJsonObject>
#include <QDir>
#include <QStandardPaths>
#include <cstdlib>

static void *tryLoad(const QString &path)
{
    if (QFile::exists(path))
        return dlopen(path.toUtf8().constData(), RTLD_LAZY);
    return nullptr;
}

AppMeshBridge::AppMeshBridge(QObject *parent)
    : QObject(parent)
{
    // Search order: env var, build tree, user lib, system lib
    QStringList candidates;

    if (const char *env = std::getenv("APPMESH_LIB_PATH"))
        candidates << QString::fromUtf8(env);

    candidates << QDir::cleanPath(QStringLiteral(CMAKE_SOURCE_DIR) + QStringLiteral("/../target/release/libappmesh_core.so"))
               << QDir::homePath() + QStringLiteral("/.local/lib/libappmesh_core.so")
               << QStringLiteral("/usr/local/lib/libappmesh_core.so");

    for (const auto &path : candidates) {
        m_handle = tryLoad(path);
        if (m_handle)
            break;
    }

    if (!m_handle)
        return;

    m_portOpen = reinterpret_cast<PortOpenFn>(dlsym(m_handle, "appmesh_port_open"));
    m_portExecute = reinterpret_cast<PortExecuteFn>(dlsym(m_handle, "appmesh_port_execute"));
    m_portFree = reinterpret_cast<PortFreeFn>(dlsym(m_handle, "appmesh_port_free"));
    m_stringFree = reinterpret_cast<StringFreeFn>(dlsym(m_handle, "appmesh_string_free"));

    if (!m_portOpen || !m_portExecute || !m_portFree || !m_stringFree) {
        dlclose(m_handle);
        m_handle = nullptr;
    }
}

AppMeshBridge::~AppMeshBridge()
{
    if (m_handle)
        dlclose(m_handle);
}

QStringList AppMeshBridge::ports() const
{
    return {
        QStringLiteral("clipboard"),
        QStringLiteral("input"),
        QStringLiteral("notify"),
        QStringLiteral("screenshot"),
        QStringLiteral("windows")
    };
}

QVariantMap AppMeshBridge::portExecute(const QString &port, const QString &cmd,
                                       const QVariantMap &args)
{
    if (!m_handle)
        return {{QStringLiteral("error"), QStringLiteral("Library not loaded")}};

    // Open port
    QByteArray portName = port.toUtf8();
    appmesh_port_t portHandle = m_portOpen(portName.constData());
    if (!portHandle)
        return {{QStringLiteral("error"), QStringLiteral("Failed to open port: ") + port}};

    // Convert args to JSON
    QJsonDocument argsDoc(QJsonObject::fromVariantMap(args));
    QByteArray argsJson = argsDoc.toJson(QJsonDocument::Compact);

    // Execute
    QByteArray cmdBytes = cmd.toUtf8();
    char *resultJson = m_portExecute(portHandle, cmdBytes.constData(), argsJson.constData());

    // Close port
    m_portFree(portHandle);

    if (!resultJson)
        return {{QStringLiteral("error"), QStringLiteral("Null result from port")}};

    // Parse result JSON
    QJsonDocument resultDoc = QJsonDocument::fromJson(QByteArray(resultJson));
    m_stringFree(resultJson);

    if (resultDoc.isNull())
        return {{QStringLiteral("error"), QStringLiteral("Invalid JSON from port")}};

    return resultDoc.object().toVariantMap();
}
