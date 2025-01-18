import React, { useState, useEffect } from "react";
import { Divider, Modal, List, Progress, Layout, Button, Spin, Space, Typography } from "antd";
import { LoadingOutlined } from "@ant-design/icons";
import { useStateValue } from "../../Utils/StateProvider";
import { rescanDir, searchFileBySingleDir, truncateUnlistedFile } from "../../Utils/Data";
import Axios from 'axios';
import * as Types from "../../Utils/actionType";


const { Title } = Typography;
const { Content } = Layout;

function DirectoryModal() {
    const [stateValue, dispatch] = useStateValue();
    // Local state management isDirModalOpen
    const [scanRubbishDirList, setScanRubbishDirList] = useState([]);
    const [scanRubbishDirLoading, setScanRubbishDirLoading] = useState(false);
    const [progressBar, setProgressBar] = useState(0);
    const [progressTotal, setProgressTotal] = useState(0); // Set default total for progress
    const [buttonSpain, setButtonSpain] = useState(null);
    const [scanDirNextSchedule, setScanDirNextSchedule] = useState("");

    // Close modal
    const handleDirModalCancel = () => {
        dispatch({
            type: Types.GENERAL_DATA,
            generalData: {
                ...stateValue.generalData,
                isDirModalOpen: false,
            },
        });
    };

    // Handle rescanning a directory
    const handleDirRescan = async (dir = "all") => {
        setScanRubbishDirLoading(true);
        try {
            const dirList = await rescanDir({ dir });
            setScanRubbishDirList(dirList.data.thedirlist);
        } finally {
            setScanRubbishDirLoading(false);
        }
    };

    const processDirectory = () => {
        var params = new URLSearchParams();
        params.append('action', 'immediately_search_rubbish_file');
        params.append('nonce', tsmltParams.tsmlt_wpnonce);

        Axios
            .post(tsmltParams.ajaxUrl, params )
            .then((response) => {
                if (response && response.data) {
                    const { dirList } = response.data.data;
                   setScanRubbishDirList(dirList);
                   const list = Object.entries( dirList ).map(([key]) => key);
                   //  console.log( 'list', list );
                    setProgressBar(
                        Math.floor((100 * (progressTotal - list.length)) / progressTotal)
                    );
                } else {
                    console.error("Invalid response structure:", response);
                }
            })
            .catch((error) => {
                console.error("Request failed:", error);
            });
    }

    const handleDirScanManually = () => {
        setButtonSpain("bulkScan");
        processDirectory();
    };

    const antIcon = <LoadingOutlined style={{ fontSize: 24 }} spin />;
    useEffect(() => {
        setScanRubbishDirList(stateValue.generalData.scanRubbishDirList);
    }, [stateValue.generalData.scanRubbishDirList ] );

    useEffect(() => {
        const list = Object.entries( scanRubbishDirList ).map(([key]) => key);
        if ( list.length > 0 ){
            processDirectory();
        } else {
            setButtonSpain(null);
        }
    }, [scanRubbishDirList] );

    console.log( 'scanRubbishDirList', scanRubbishDirList );
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
                        await handleDirRescan("all");
                        await truncateUnlistedFile();
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
                    type={"primary"}
                >
                    Re-Search Directory
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
                {scanRubbishDirLoading ? (
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
                    <List
                        itemLayout="horizontal"
                        dataSource={Object.entries(scanRubbishDirList)}
                        locale={{
                            emptyText: (
                                <Title level={5} style={{ margin: "0 15px", color: "red" }}>
                                    Directory will search in the next schedule. Please be patient.
                                    <br />
                                    Next Schedule: {scanDirNextSchedule}
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
                                            <span style={{ color: "#1677ff" }}> Scanned {item.counted} items of {item.total_items} items</span>
                                        )
                                    }
                                />
                                <Space>
                                    <Button
                                        key="rescan"
                                        onClick={() => handleDirRescan(key)}
                                        type= "primary"
                                    >
                                        Re-Execute in Schedule
                                    </Button>
                                </Space>
                            </List.Item>
                        )}
                    />
                )}
            </Content>
            {progressBar > 0 && (
                <>
                    <Title level={5}>Progress:</Title>
                    <Progress showInfo percent={progressBar} />
                </>
            )}
            <Divider />
        </Modal>
    );
}

export default DirectoryModal;