import React, { useState, useEffect } from "react";
import {  
  useEstimateGas,
  useSendTransaction,
  useWaitForTransactionReceipt,
  useAccount,
  useTransaction,
} from "wagmi";
import { parseEther } from "viem";
import {
  displayPopUp,
  getDynamicTransactionData, 
  PaymentInProcess,
  ConfirmTransaction,
  PaymentUi,
  handleSwitchNetworkMessage,
  PaymentLoader,
  FetchPrevTx,
  PaymentPendingUi
} from "../component/helper";
import {
  restApiSaveTransaction,
  restApiConfirmTransaction,
  cancelOrder,
  restApiSaveTransactionHash,
} from "../component/handelRestApi";
import { useModal } from "connectkit";

const SendTransaction = ({ config }) => {
  const {
    receiver,
    in_crypto,
    process_msg,
    wallet_image,
    const_msg,
    block_explorer,
    is_paid,
    order_status,
    currency_symbol,
    connectedWallet,
    confirm_msg,
    place_order_btn,
    currency_logo,
    fiatSymbol,
    network_name,
    totalFiat,
    without_discount,
    decimalchainId, // Add the correct network here
  } = extradataRest;

  const [getSaveResponse, setSaveResponse] = useState(null);
  const [runOnce, setRunOnce] = useState(null);
  const [rejectedTransaction, setRejectedTransaction] = useState(null);
  const { open, setOpen } = useModal();
  const { address, chain } = useAccount();
  const [saveHash, setSaveHash] = useState(false);
  const [prevPaymentHash, setPrevPaymentHash] = useState(false);
  const [prevPaymentFetch, setPrevPaymentFetch] = useState(false);

  const { data : estimateGas } = useEstimateGas({
    to: receiver,
    value: parseEther(in_crypto),
  });

  const {sendTransaction, error, data} = useSendTransaction(); 
  if (open && chain?.id !== decimalchainId) {
    handleSwitchNetworkMessage(const_msg);
  }

  // Confirm transaction callback function
  const confirmTransaction=(txData,saveResponse)=>{
    const response = getDynamicTransactionData(
      txData,
      chain?.id,
      currency_symbol
    );
    restApiConfirmTransaction(response, saveResponse, extradataRest);
  }

  //Hide the chain change popup
  useEffect(() => {
    if (chain?.id === decimalchainId) {
      setOpen(false);
    }
  }, [chain?.id]);

  // Fetch previous transaction status if exist then complete previous transaction.
  useEffect(async () => {
    if (!prevPaymentFetch) {
      const staticData = {
        from: address.toLowerCase(),
        amount: in_crypto,
        recever: receiver.toLowerCase(),
        token_address: currency_symbol
      };

      const proccessData={wallet_image,process_msg,block_explorer,const_msg,config};

      const data= await FetchPrevTx(staticData,confirmTransaction,extradataRest,proccessData);

      data.status === true && setPrevPaymentFetch(true);
      data.txId && setPrevPaymentHash(data.txId);

    }
  }, [])

  //Get initilas transaction details using hash
  const saveHashResponse = useTransaction({ hash: data });
    // Save hash response & sender id in order page.
    useEffect(() => {
      if (data && !runOnce && !saveHash) {
        const staticData = {
          hash: data,
          from: address.toLowerCase(),
          amount: in_crypto,
          recever: receiver.toLowerCase(),
          token_address: currency_symbol
        };
  
        restApiSaveTransactionHash(staticData, extradataRest).then(() => {
          setSaveHash(true);
        })
      }
    }, [data && !saveHashResponse.data])

  //Save the initial transaction detilas in database
  useEffect(() => {
    if (data && !runOnce && saveHashResponse.data) {
      PaymentInProcess(
        wallet_image,
        process_msg,
        block_explorer,
        saveHashResponse,
        const_msg
      );
      const response = getDynamicTransactionData(
        saveHashResponse.data,
        chain?.id,
        currency_symbol
      );
      restApiSaveTransaction(response, extradataRest).then(function (backData) {
        setSaveResponse(backData);
        setRunOnce(true);
      });
    }
  }, [data && saveHashResponse.data]);
  //Wait for transaction completetion
  const waitFordata = useWaitForTransactionReceipt({
    hash: data,
  });
  // Get confirmed transaction details using hash
  const saveConfirmResponse = useTransaction({
    hash: waitFordata.data?.transactionHash,
  });
  //Confirm the transaction & process order after block confirmation
  useEffect(() => {
    if (waitFordata.data?.transactionHash && getSaveResponse) {
      setTimeout(()=>{
        confirmTransaction(saveConfirmResponse.data,getSaveResponse);
      },3000);
    }
  }, [saveConfirmResponse.data && getSaveResponse]);
  //auto open the payment module

  useEffect(() => {
    const isPageReloaded =
      performance.getEntriesByType("navigation")[0].type === "reload";

    if (isPageReloaded) {
    } else {
      if (
        !prevPaymentHash &&
        prevPaymentFetch &&
        sendTransaction &&
        !is_paid &&
        order_status !== "cancelled" &&
        !open
      ) {
        handleTransaction();
      }
    }
  }, [sendTransaction,prevPaymentFetch]);

  //if any error occur during payment process
  useEffect(() => {
    if (error) {
      if (error?.shortMessage ==='User rejected the request.') {
      cancelOrder(extradataRest);
      setRejectedTransaction(true);
    } else {
      displayPopUp({
        msg: error?.shortMessage,
        image: wallet_image,
        time: 5000,
      });
    }
    }
  }, [error]);
  //Send transaction function handling
  const handleTransaction = () => {
    ConfirmTransaction(wallet_image, confirm_msg, const_msg);
    sendTransaction({
      gas : estimateGas,
      to: receiver,
      value: parseEther(in_crypto),
    })
  };

 
  return (
    <>
    {prevPaymentFetch && prevPaymentHash &&
        <PaymentPendingUi
          wallet_image={wallet_image}
          connectedWallet={connectedWallet}
          const_msg={const_msg}
          address={address}
          txId={prevPaymentHash}
        />}
      {prevPaymentFetch && !prevPaymentHash && !is_paid &&
        order_status !== "cancelled" &&
        !waitFordata.isSuccess &&
        !rejectedTransaction && (
          <PaymentUi
            wallet_image={wallet_image}
            connectedWallet={connectedWallet}
            const_msg={const_msg}
            address={address}
            without_discount={without_discount}
            currency_symbol={currency_symbol}
            in_crypto={in_crypto}
            network_name={network_name}
            currency_logo={currency_logo}
            fiatSymbol={fiatSymbol}
            totalFiat={totalFiat}
            place_order_btn={place_order_btn}
            handleTransaction={handleTransaction}
            cancelOrder={cancelOrder}
          />
        )}
      {(rejectedTransaction || !prevPaymentFetch) && (
        <PaymentLoader/>
      )}
    </>
  );
};
export default SendTransaction;
