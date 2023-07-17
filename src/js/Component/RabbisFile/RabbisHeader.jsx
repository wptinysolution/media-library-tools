
import { Typography, Layout, Button, Space } from 'antd';

import { headerStyle } from "../../Utils/UtilData";

import { useStateValue } from "../../Utils/StateProvider";

const { Header } = Layout;

const { Title } = Typography;

function RabbisHeader() {

    const [ stateValue, dispatch ] = useStateValue();

    return (
        <Header style={{...headerStyle, height: 'inherit'}}>

            <Space wrap>
                <Button
                    style={{
                        width: '180px'
                    }}
                    type="primary"
                    size="large"
                    ghost={ 1 }>  { 'Delete Rabbisd File' }
                </Button>

                <Title level={5} style={{
                    margin:'0 15px',
                    color: 'red'
                }}> Rabbis File Note : A "Rabbis File" refers to a file that exists within a directory but is not included in the media library or database. </Title>
            </Space>
        </Header>
    );
}

export default RabbisHeader;