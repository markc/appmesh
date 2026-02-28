#ifndef APPMESHPLUGIN_H
#define APPMESHPLUGIN_H

#include <QObject>
#include <QVariantMap>
#include <QStringList>
#include <QtQml/qqmlregistration.h>
#include <dlfcn.h>

// Type definitions from appmesh.h
typedef void *appmesh_port_t;

// Function pointer types for the port API
using PortOpenFn = appmesh_port_t (*)(const char *name);
using PortExecuteFn = char *(*)(appmesh_port_t port, const char *cmd, const char *args_json);
using PortFreeFn = void (*)(appmesh_port_t port);
using StringFreeFn = void (*)(char *s);

class AppMeshBridge : public QObject
{
    Q_OBJECT
    QML_ELEMENT
    QML_SINGLETON

    Q_PROPERTY(bool available READ available CONSTANT)
    Q_PROPERTY(QStringList ports READ ports CONSTANT)

public:
    explicit AppMeshBridge(QObject *parent = nullptr);
    ~AppMeshBridge();

    bool available() const { return m_handle != nullptr; }
    QStringList ports() const;

    Q_INVOKABLE QVariantMap portExecute(const QString &port, const QString &cmd,
                                        const QVariantMap &args = {});
    Q_INVOKABLE void sendMessage(const QString &channel, const QString &data);

signals:
    void meshMessage(const QString &channel, const QString &data);

private:
    void *m_handle = nullptr; // dlopen handle
    PortOpenFn m_portOpen = nullptr;
    PortExecuteFn m_portExecute = nullptr;
    PortFreeFn m_portFree = nullptr;
    StringFreeFn m_stringFree = nullptr;
};

#endif // APPMESHPLUGIN_H
