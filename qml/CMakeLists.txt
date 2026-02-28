cmake_minimum_required(VERSION 3.28)
project(appmesh-qml VERSION 0.1 LANGUAGES CXX)

set(CMAKE_CXX_STANDARD 20)
set(CMAKE_CXX_STANDARD_REQUIRED ON)
set(CMAKE_AUTOMOC ON)

find_package(Qt6 REQUIRED COMPONENTS Core Qml Quick)
qt_standard_project_setup(REQUIRES 6.5)

qt_add_executable(appmesh-qml main.cpp)

qt_add_qml_module(appmesh-qml
    URI AppMesh
    VERSION 1.0
    SOURCES appmeshplugin.h appmeshplugin.cpp
    QML_FILES Main.qml
)

target_link_libraries(appmesh-qml PRIVATE Qt6::Core Qt6::Qml Qt6::Quick dl)
target_include_directories(appmesh-qml PRIVATE ${CMAKE_SOURCE_DIR}/../crates/appmesh-core)
target_compile_definitions(appmesh-qml PRIVATE CMAKE_SOURCE_DIR="${CMAKE_SOURCE_DIR}")
