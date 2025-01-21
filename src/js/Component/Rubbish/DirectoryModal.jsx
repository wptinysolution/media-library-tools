import React, { useState, useEffect } from "react";
import {Divider, Modal, List, Progress, Layout, Button, Spin, Space, Typography, Checkbox} from "antd";
import { LoadingOutlined } from "@ant-design/icons";
import { useStateValue } from "../../Utils/StateProvider";
import { rescanDir, searchFileBySingleDir, truncateUnlistedFile } from "../../Utils/Data";
import Axios from 'axios';
import * as Types from "../../Utils/actionType";


const { Title } = Typography;
const { Content } = Layout;

function DirectoryModal() {
    const [stateValue, dispatch] = useStateValue();
    const [dirListExist, setDirListExist] = useState([]);
    const [scanRubbishDirList, setScanRubbishDirList] = useState([]);
    const [scanRubbishDirLoading, setScanRubbishDirLoading] = useState(false);
    const [progressBar, setProgressBar] = useState(0);
    const [progressTotal, setProgressTotal] = useState(0); // Set default total for progress
    const [buttonSpain, setButtonSpain] = useState(null);
    const [instantDeletion, setInstantDeletion] = useState('not-instant');

    const [skip, setSkip] = useState([]);

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
    /**
     *
     * @param dir
     * @returns {Promise<void>}
     */
    const exclude_from_bulk_scan = (dir = "") => {
        setSkip( [
            ...skip,
            dir
        ])
    };

    const processDirectory = () => {
        var params = new URLSearchParams();
        params.append('action', 'immediately_search_rubbish_file');
        params.append('nonce', tsmltParams.tsmlt_wpnonce);
        params.append('instantDeletion', instantDeletion );
        // Append the array `skip` properly
        skip.forEach((value) => {
            params.append('skip[]', value); // Use `skip[]` to match PHP's expected array structure
        });
        console.log('skip', skip );
        Axios
            .post(tsmltParams.ajaxUrl, params )
            .then((response) => {
                if (response && response.data) {
                    const { dirList, dirStatusList } = response.data.data;
                   setScanRubbishDirList(dirStatusList);
                   const list = Object.entries( dirList ).map(([key]) => key);
                   const percent =  Math.floor((100 * (progressTotal - list.length)) / progressTotal);
                    setProgressBar( percent );
                    setDirListExist(list);
                    console.log('percent', percent );
                    if (percent >= 100 ){
                        window.location.reload();
                    }
                } else {
                    console.error("Invalid response structure:", response);
                }
            })
            .catch((error) => {
                console.error("Request failed:", error);
                console.log("Retrying directory processing in 1 second...");
                setTimeout(() => {
                    processDirectory();
                }, 2000);
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
        setProgressTotal(list.length);
    }, [scanRubbishDirList] );
    useEffect(() => {
        if ( dirListExist.length > 0 ){
            processDirectory();
        }
    }, [scanRubbishDirList] );

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
                <span>
                    { tsmltParams.hasExtended ?
                        <Checkbox onChange={ ( event ) => setInstantDeletion( event.target.checked ? 'instant' : 'not-instant' ) }>
                            <span style={{color:'green'}}> Rubbish File Instant Deletion During Bulk Scan </span>
                        </Checkbox> :
                        null
                    }
                </span>,
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
                    type={"default"}
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
                    borderBottom: '1px solid #eee'
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
                                    Directory will search in the next schedule. Please be patient
                                </Title>
                            ),
                        }}
                        renderItem={([key, item]) => {
                            const skippedItem = skip.includes(key);
                            return (
                            <List.Item
                                style={skippedItem ? { opacity: 0.2 } : {}}
                                key={key}>
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
                                        key="exclude"
                                        onClick={() => exclude_from_bulk_scan(key)}
                                        type= "primary"
                                    >
                                        Exclude from Bulk Scan
                                    </Button>
                                    <Button
                                        key="rescan"
                                        onClick={() => handleDirRescan(key)}
                                        type= "primary"
                                    >
                                        Re-Execute in Schedule
                                    </Button>
                                </Space>
                            </List.Item>
                        )}}
                    />
                )}
            </Content>
            {progressBar > 0 && (
                <>
                    <Title style={{
                        margin: '10px 0'
                    }} level={5}>Directory Scanning Progress:</Title>
                    <Progress showInfo percent={progressBar} />
                </>
            )}
            <Divider style={{
                margin: '5px 0'
            }} />
        </Modal>
    );
}

export default DirectoryModal;