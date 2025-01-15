import React, { useState } from "react";
import { Divider, Modal, List, Progress, Layout, Button, Spin, Space, Typography } from "antd";
import { LoadingOutlined } from "@ant-design/icons";
import { useStateValue } from "../../Utils/StateProvider";
import * as Types from "../../Utils/actionType";
import { actionClearSchedule, rescanDir, searchFileBySingleDir, truncateUnlistedFile } from "../../Utils/Data";

const { Title } = Typography;
const { Content } = Layout;

function DirectoryModal() {
    const [stateValue, dispatch] = useStateValue();
    const [scanDir, setScanDir] = useState(null);
    const [buttonSpain, setButtonSpain] = useState(null);

    // Utility to dispatch general data updates
    const updateGeneralData = (data) => {
        dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                ...data,
            },
        });
    };

    // Utility to update bulk submit data
    const updateBulkSubmitData = (data) => {
        dispatch({
            type: Types.BULK_SUBMIT,
            bulkSubmitData: {
                ...stateValue.bulkSubmitData,
                ...data,
            },
        });
    };

    const handleDirModalCancel = () => {
        updateGeneralData({ isDirModalOpen: false });
    };

    const handleDirRescan = async (dir = "all") => {
        setScanDir(dir);
        updateGeneralData({ scanRubbishDirLoading: true });

        try {
            const dirList = await rescanDir({ dir });
            updateGeneralData({
                scanRubbishDirList: dirList.data.thedirlist,
                scanRubbishDirLoading: false,
            });
        } finally {
            setScanDir(null);
        }
    };

    const searchFileBySingleDirRecursively = async (prams) => {
        setButtonSpain("bulkScan");
        updateBulkSubmitData({
            progressBar: Math.floor(
                (100 * (stateValue.bulkSubmitData.progressTotal - prams.length)) / stateValue.bulkSubmitData.progressTotal
            ),
        });
        if (prams.length <= 0) {
            setTimeout(() => {
                setButtonSpain(null);
                setScanDir(null);
            }, 1000);
            return;
        }
        const dirKey = prams[0];
        setScanDir(dirKey);
        try {
            const response = await searchFileBySingleDir({ directory: dirKey });
            if (response?.status === 200) {
                const { dirlist, nextDir } = response.data;
                updateGeneralData({
                    scanRubbishDirList: dirlist,
                    scanRubbishDirLoading: false,
                });
                const nextPrams = nextDir === "nextDir" ? prams.slice(1) : prams;
                await searchFileBySingleDirRecursively(nextPrams);
            }
        } catch (error) {
            console.error("Error processing directory:", dirKey, error);
        } finally {
            if (prams.length === 1) {
                setTimeout(() => {
                    setButtonSpain(null);
                    setScanDir(null);
                }, 1000);
            }
        }
    };

    const handleDirScanManually = async () => {
        setButtonSpain("bulkScan");
        const dirList = Object.entries(stateValue.generalData.scanRubbishDirList).map(([key]) => key);
        await searchFileBySingleDirRecursively(dirList);
    };

    const antIcon = <LoadingOutlined style={{ fontSize: 24 }} spin />;

    return (
        <Modal
            style={{ maxWidth: "950px" }}
            className="rubbish-scan-directory-modal"
            width="100%"
            height="500px"
            title="Directory List"
            open={stateValue.generalData.isDirModalOpen}
            onCancel={handleDirModalCancel}
            footer={[
                <Button
                    key="removeOld"
                    onClick={async () => {
                        await truncateUnlistedFile();
                        await handleDirRescan("all");
                        window.location.reload();
                    }}
                >
                    Delete Old History
                </Button>,
                <Button
                    key="rescanManually"
                    onClick={handleDirScanManually}
                    type={buttonSpain === "bulkScan" ? "primary" : "default"}
                >
                    Search Immediately {buttonSpain === "bulkScan" && <Spin size="small" />}
                </Button>,
                <Button
                    key="rescan"
                    onClick={() => handleDirRescan("all")}
                    type={scanDir === "all" ? "primary" : "default"}
                >
                    Re-Search Directory {scanDir === "all" && <Spin size="small" />}
                </Button>,
            ]}
        >
            <Divider />
            <Content
                style={{
                    height: "450px",
                    position: "relative",
                    overflowY: "auto",
                    padding: "0 15px",
                }}
            >
                {stateValue.generalData.scanRubbishDirLoading ? (
                    <Spin
                        indicator={antIcon}
                        style={{
                            position: "absolute",
                            left: "50%",
                            top: "50%",
                            margin: "auto",
                            transform: "translate(-50%, -50%)",
                        }}
                    />
                ) : (
                    <>
                        <List
                            itemLayout="horizontal"
                            dataSource={Object.entries(stateValue.generalData.scanRubbishDirList)}
                            locale={{
                                emptyText: (
                                    <Title level={5} style={{ margin: "0 15px", color: "red" }}>
                                        Directory will search in the next schedule. Please be patient.
                                        <br />
                                        Next Schedule: {stateValue.generalData.scanDirNextSchedule}
                                    </Title>
                                ),
                            }}
                            renderItem={([key, item]) => (
                                <List.Item key={key}>
                                    <List.Item.Meta
                                        title={key}
                                        description={
                                            item.total_items === 0 ? (
                                                "This directory will be scanned again according to the schedule."
                                            ) : (
                                                <span style={{ color: "#1677ff" }}>
                          Scanned {item.counted} items of {item.total_items} items
                        </span>
                                            )
                                        }
                                    />
                                    <Space>
                                        <Button
                                            key="rescan"
                                            onClick={() => handleDirRescan(key)}
                                            type={key === scanDir ? "primary" : "default"}
                                        >
                                            Re-Execute in Schedule {key === scanDir && <Spin size="small" />}
                                        </Button>
                                    </Space>
                                </List.Item>
                            )}
                        />
                    </>
                )}
            </Content>
            {stateValue.bulkSubmitData.progressBar > 0 && (
                <>
                    <Title level={5}>Progress:</Title>
                    <Progress showInfo percent={stateValue.bulkSubmitData.progressBar} />
                </>
            )}
            <Divider />
        </Modal>
    );
}

export default DirectoryModal;