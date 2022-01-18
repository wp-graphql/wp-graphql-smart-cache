import { Modal } from 'antd';
import { useState } from '@wordpress/element';

const ConfirmModal = ({ visible }) => {

    return (
        <Modal
            visible={visible}
        />
    )

}

export default ConfirmModal;